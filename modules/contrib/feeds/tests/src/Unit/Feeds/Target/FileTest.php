<?php

namespace Drupal\Tests\feeds\Unit\Feeds\Target;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Utility\Token;
use Drupal\feeds\EntityFinderInterface;
use Drupal\feeds\Exception\EmptyFeedException;
use Drupal\feeds\Feeds\Target\File;
use GuzzleHttp\ClientInterface;

/**
 * @coversDefaultClass \Drupal\feeds\Feeds\Target\File
 * @group feeds
 */
class FileTest extends FieldTargetTestBase {

  /**
   * The entity type manager prophecy used in the test.
   *
   * @var \Prophecy\Prophecy\ProphecyInterface|\Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The http client prophecy used in the test.
   *
   * @var \Prophecy\Prophecy\ProphecyInterface|\GuzzleHttp\ClientInterface
   */
  protected $client;

  /**
   * Token service.
   *
   * @var \Prophecy\Prophecy\ProphecyInterface|\Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * The file and stream wrapper helper.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The Feeds entity finder service.
   *
   * @var \Drupal\feeds\EntityFinderInterface
   */
  protected $entityFinder;

  /**
   * The FeedsTarget plugin being tested.
   *
   * @var \Drupal\feeds\Feeds\Target\File
   */
  protected $targetPlugin;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->entityTypeManager = $this->prophesize(EntityTypeManagerInterface::class);
    $this->client = $this->prophesize(ClientInterface::class);
    $this->token = $this->prophesize(Token::class);
    $this->entityFieldManager = $this->prophesize(EntityFieldManagerInterface::class);
    $this->entityFieldManager->getFieldStorageDefinitions('file')->willReturn([]);
    $this->entityFinder = $this->prophesize(EntityFinderInterface::class);
    $this->fileSystem = $this->prophesize(FileSystemInterface::class);

    // Made-up entity type that we are referencing to.
    $referenceable_entity_type = $this->prophesize(EntityTypeInterface::class);
    $referenceable_entity_type->getKey('label')->willReturn('file label');
    $this->entityTypeManager->getDefinition('file')->willReturn($referenceable_entity_type)->shouldBeCalled();

    $method = $this->getMethod('Drupal\feeds\Feeds\Target\File', 'prepareTarget')->getClosure();
    $field_definition_mock = $this->getMockFieldDefinition([
      'display_field' => 'false',
      'display_default' => 'false',
      'uri_scheme' => 'public',
      'target_type' => 'file',
      'file_directory' => '[date:custom:Y]-[date:custom:m]',
      'file_extensions' => 'pdf doc docx txt jpg jpeg ppt xls png',
      'max_filesize' => '',
      'description_field' => 'true',
      'handler' => 'default:file',
      'handler_settings' => [],
    ]);

    $configuration = [
      'feed_type' => $this->createMock('Drupal\feeds\FeedTypeInterface'),
      'target_definition' => $method($field_definition_mock),
    ];
    $this->targetPlugin = new File($configuration, 'file', [], $this->entityTypeManager->reveal(), $this->client->reveal(), $this->token->reveal(), $this->entityFieldManager->reveal(), $this->entityFinder->reveal(), $this->fileSystem->reveal());
  }

  /**
   * {@inheritdoc}
   */
  protected function getTargetClass() {
    return File::class;
  }

  /**
   * @covers ::prepareValue
   * @dataProvider dataProviderPrepareValue
   */
  public function testPrepareValue($expected, array $values, $expected_exception = NULL) {
    $method = $this->getProtectedClosure($this->targetPlugin, 'prepareValue');

    if ($expected_exception) {
      $this->expectException($expected_exception);
    }

    $method(0, $values);
    foreach ($expected as $key => $value) {
      $this->assertEquals($value, $values[$key]);
    }
  }

  /**
   * Data provider for testPrepareValue().
   */
  public function dataProviderPrepareValue() {
    return [
      // Description.
      [
        'expected' => [
          'description' => 'mydescription',
          'display' => FALSE,
        ],
        'values' => [
          'description' => 'mydescription',
        ],
      ],

      // Empty file target value.
      [
        'expected' => [],
        'values' => [
          'target_id' => '',
        ],
        'expected_exception' => EmptyFeedException::class,
      ],
    ];
  }

}
