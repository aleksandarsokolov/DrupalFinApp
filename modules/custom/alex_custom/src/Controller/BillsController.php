<?php

/**
 * @file
 * Contains \Drupal\alex_custom\Controller\BillsController.
 */

namespace Drupal\alex_custom\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;

class BillsController  extends ControllerBase {    

  // Timestamp	Date	Product	Brand	Type	Planned	Amount	PaymentType	Company	Location	Tags	Month	Year	Bill	ProductID
  //     0        1        2    3     4       5      6         7         8        9      10     11   12    13       14

    public function loadbills() {

      try {
        $billproductrows = array();
        $billproductrows = $this->getCsvRows();

        $bill = $this->createEmptyBill();

        $products = array();

        // Loop through all the records
        foreach ($billproductrows as $row) {
            
            $billtitle = $row[13];

            // if the billtitle is different than the record
            if ($bill->getTitle() != $billtitle) {
                if ($bill->getTitle() != '') {
                    $bill->save();
          
                    $bill = $this->createEmptyBill();
                    $bill->setTitle($billtitle);
                } else {
                    $bill = $this->createEmptyBill();
                    $bill->setTitle($billtitle);
                }

                $products = array();

                $billdatestring  = $row[1]; 
                $date =  date_create($billdatestring);
                $billpayment = $row[7];
                $billcompany  = $row[8];
                $billlocation = $row[9];
                $billmonth = $row[11];
                $billyear = $row[12];

                if($billlocation!=''){
                    $bill->field_billing_location->target_id = $this->getTermId($billlocation,'billing_location');
                }

                if($billcompany!=''){
                    $bill->field_billing_company->target_id = $this->getTermId($billcompany,'company');
                }


                $bill->field_billing_date = $date->format('Y-m-d');
                $bill->field_billing_payment_type = $billpayment;
                $bill->field_billing_year = $billyear;
                $bill->field_billing_month = $billmonth;
            } 

            $productid = $this->getProduct($row);
            $bill->field_billing_products[] = ['target_id' => $productid];
   
        }

        // Save the last object
        $bill->save();
      } catch (Exception $e) {
          $message =  $e->getMessage();
      }
    }

  /**
   * {@inheritdoc}
   */
  public function getTermId($term, $vocabulary){
    // $term1 = taxonomy_term_load_multiple_by_name($term,$vocabulary);
    $term1 = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadByProperties([
        'vid' => $vocabulary,
        'name' => $term,
      ]);
    if(count($term1) == 0) {
        $term1 = Term::create([
            'name' => $term, 
            'vid' => $vocabulary,
        ]);
        $term1->save();
    }
    $termelem = reset($term1);
    return $termelem->id();
  }


   public function createEmptyBill() {
       
        $bill = Node::create([
            'type' => 'billing_bill',
            'title' => '',
            'field_billing_location'  => [
                ['target_id' => 0]
            ],
            'field_billing_company'  => [
                ['target_id' => 0]
            ],
        ]);

        return $bill;
   }

  public function getCsvRows() {
    $return = [];

    $csvpath = \Drupal::service('extension.list.module')->getPath('alex_custom') . '/src/Products.csv';
    // $csvpath = drupal_get_path('module', 'alex_custom') . '/src/Products.csv';

    if (($csv = fopen($csvpath, 'r')) !== FALSE) {
      while (($row = fgetcsv($csv, 0, ',')) !== FALSE) {
        $return[] = $row;
        // generateBill($row);

      }

      fclose($csv);
    }

    return $return;
  }

  public function getProduct(&$row) {
    
    $product = Node::create([
      'type' => 'billing_product',
      'title' => $row[14] . $row[2],
      'field_billing_product_type' => [
        ['target_id' => 0]
      ],
      'field_billing_planned' => $row[5],
      'field_billing_price' => $row[6],
      'field_billing_product_name' => [
        ['target_id' => 0]
      ],
      'field_billing_brand'  => [
        ['target_id' => 0]
      ],
    ]);

    //Create the initial product
    if($row[2] != ''){
        $product->field_billing_product_name->target_id = $this->getTermId($row[2],'product_taxonomy');
    }

    // Save product brand
    if($row[3] != ''){
        $product->field_billing_brand->target_id = $this->getTermId($row[3],'billing_brand');
    }

    // Save product type
    if($row[4] != ''){
        $product->field_billing_product_type->target_id = $this->getTermId($row[4],'billing_product_type');
    }

    // Save product tags
    if($row[10] != ''){
        $product->field_billing_tag->target_id = $this->getTermId($row[10],'tags');
    }
    $product->save();

    return $product->id();
  }

}
