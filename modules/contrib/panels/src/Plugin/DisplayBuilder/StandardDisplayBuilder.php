<?php

namespace Drupal\panels\Plugin\DisplayBuilder;

use Drupal\Component\Utility\Html;
use Drupal\Core\Block\TitleBlockPluginInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\Context\ContextHandlerInterface;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Session\AccountInterface;
use Drupal\ctools\Plugin\PluginWizardInterface;
use Drupal\panels\Form\PanelsContentForm;
use Drupal\panels\Plugin\DisplayVariant\PanelsDisplayVariant;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The standard display builder for viewing a PanelsDisplayVariant.
 *
 * @DisplayBuilder(
 *   id = "standard",
 *   label = @Translation("Standard")
 * )
 */
class StandardDisplayBuilder extends DisplayBuilderBase implements PluginWizardInterface, ContainerFactoryPluginInterface {

  /**
   * The context handler.
   *
   * @var \Drupal\Core\Plugin\Context\ContextHandlerInterface
   */
  protected $contextHandler;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs a new PanelsDisplayVariant.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Plugin\Context\ContextHandlerInterface $context_handler
   *   The context handler.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ContextHandlerInterface $context_handler, AccountInterface $account, ModuleHandlerInterface $module_handler) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->contextHandler = $context_handler;
    $this->account = $account;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('context.handler'),
      $container->get('current_user'),
      $container->get('module_handler')
    );
  }

  /**
   * Build render arrays for each of the regions.
   *
   * @param array $regions
   *   The render array representing regions.
   * @param array $contexts
   *   The array of context objects.
   * @param string $title
   *   The page title.
   *
   * @return array
   *   An associative array, keyed by region ID, containing the render arrays
   *   representing the content of each region.
   */
  protected function buildRegions(array $regions, array $contexts, $title = NULL) {
    $build = [];
    foreach ($regions as $region => $blocks) {
      if (!$blocks) {
        continue;
      }

      $region_name = Html::getClass("block-region-$region");
      $build[$region]['#prefix'] = '<div class="' . $region_name . '">';
      $build[$region]['#suffix'] = '</div>';

      /** @var \Drupal\Core\Block\BlockPluginInterface[] $blocks */
      $weight = 0;
      foreach ($blocks as $block_id => $block) {
        if ($block instanceof ContextAwarePluginInterface) {
          $this->contextHandler->applyContextMapping($block, $contexts);
        }
        if ($block instanceof TitleBlockPluginInterface) {
          $block->setTitle($title);
        }
        if ($block->access($this->account)) {
          $configuration = $block->getConfiguration();
          $block_render_array = [
            '#theme' => 'block',
            '#attributes' => [],
            '#contextual_links' => [],
            '#weight' => $weight++,
            '#configuration' => $configuration,
            '#plugin_id' => $block->getPluginId(),
            '#base_plugin_id' => $block->getBaseId(),
            '#derivative_plugin_id' => $block->getDerivativeId(),
          ];

          // Build the block and bubble its attributes up if possible. This
          // allows modules like Quickedit to function.
          // See \Drupal\block\BlockViewBuilder::preRender() for reference.
          $content = $block->build();
          $this->moduleHandler->alter(['block_build', 'block_build_' . $block->getBaseId()], $content, $block);
          if ($content !== NULL && !Element::isEmpty($content)) {
            foreach (['#attributes', '#contextual_links'] as $property) {
              if (isset($content[$property])) {
                $block_render_array[$property] += $content[$property];
                unset($content[$property]);
              }
            }
          }

          // If the block is empty, instead of trying to render the block
          // correctly return just #cache, so that the render system knows the
          // reasons (cache contexts & tags) why this block is empty.
          if (Element::isEmpty($content)) {
            $block_render_array = [];
            $cacheable_metadata = CacheableMetadata::createFromObject($block_render_array);
            $cacheable_metadata->applyTo($block_render_array);
            if (isset($content['#cache'])) {
              $block_render_array['#cache'] += $content['#cache'];
            }
          }

          $block_render_array['content'] = $content;

          // Add CSS classes.
          $css_classes = !empty($configuration['css_classes']) ? $configuration['css_classes'] : [];
          if (is_array($css_classes)) {
            foreach ($css_classes as $class) {
              $block_render_array['#attributes']['class'][] = Html::cleanCssIdentifier($class);
            }
          } elseif (is_string($css_classes)) {
            $block_render_array['#attributes']['class'][] = Html::cleanCssIdentifier($css_classes);
          }
          // Add HTML Id.
          $html_id = !empty($configuration['html_id']) ? $configuration['html_id'] : '';
          if (!empty($html_id)) {
            $block_render_array['#attributes']['id'] = Html::getId($html_id);
          }
          // Add CSS styles.
          $css_styles = !empty($configuration['css_styles']) ? $configuration['css_styles'] : '';
          if (!empty($css_styles)) {
            $block_render_array['#attributes']['style'] = $css_styles;
          }

          $this->moduleHandler->alter(['block_view', 'block_view_' . $block->getBaseId()], $block_render_array, $block);
          $build[$region][$block_id] = $block_render_array;
        }
      }
    }
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function build(PanelsDisplayVariant $panels_display) {
    $regions = $panels_display->getRegionAssignments();
    $contexts = $panels_display->getContexts();
    $layout = $panels_display->getLayout();
    $title = $panels_display->getRenderedPageTitle();

    $regions = $this->buildRegions($regions, $contexts, $title);
    if ($layout) {
      $regions = $layout->build($regions);
    }
    return $regions;
  }

  /**
   * {@inheritdoc}
   */
  public function getWizardOperations($cached_values) {
    $operations = [];
    $operations['content'] = [
      'title' => $this->t('Content'),
      'form' => PanelsContentForm::class,
    ];
    return $operations;
  }

}
