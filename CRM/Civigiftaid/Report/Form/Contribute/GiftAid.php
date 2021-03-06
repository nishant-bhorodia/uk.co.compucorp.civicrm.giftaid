<?php

/*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.3                                              |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2011                                |
 +--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
*/

/**
 *
 * @package CRM
 * @copyright CiviCRM LLC (c) 2004-2011
 * $Id$
 *
 */
require_once 'CRM/Report/Form.php';
require_once 'CRM/Civigiftaid/Utils/Contribution.php';

class CRM_Civigiftaid_Report_Form_Contribute_GiftAid extends CRM_Report_Form {

    protected $_addressField = false;
    protected $_customGroupExtends = array( 'Contribution' );

    function __construct( ) {
      $this->_columns =
        array(
          'civicrm_entity_batch' => array(
            'dao' => 'CRM_Batch_DAO_EntityBatch',
            'filters' =>
            array(
              'batch_id' => array(
                'title' => 'Batch',
                'operatorType' => CRM_Report_Form::OP_MULTISELECT,
                'options'      => CRM_Civigiftaid_Utils_Contribution::getBatchIdTitle( 'id desc' ),
              ),
            ),
          ),
          'civicrm_contribution' =>
            array(
              'dao' => 'CRM_Contribute_DAO_Contribution',
              'fields' => array(
                'contribution_id' => array(
                  'name'       => 'id',
                  'title'      => 'Contribution ID',
                  'no_display' => true,
                  'required'   => true,
                ),
                'contact_id' => array(
                  'name' => 'contact_id',
                  'title'  => 'Name of Donor',
                  'no_display' => false,
                  'required'   => true,
                ),
                'receive_date' => array(
                  'name'  => 'receive_date',
                  'title'      => 'Contribution Date',
                  'no_display' => false,
                  'required'   => true,
                ),
              ),
            ),
          'civicrm_address' =>
            array(
              'dao' => 'CRM_Core_DAO_Address',
              'grouping' => 'contact-fields',
              'fields' =>
              array(
                'street_address' => NULL,
                'city' => NULL,
                'state_province_id' => array('title' => ts('State/Province'),),
                'country_id' => array('title' => ts('Country'),),
                'postal_code' => NULL,
              ),
            ),
        );

        parent::__construct( );

        // set defaults
        if ( is_array( $this->_columns['civicrm_value_gift_aid_submission'] ) ) {
            foreach ( $this->_columns['civicrm_value_gift_aid_submission']['fields'] as $field => $values ) {
                $this->_columns['civicrm_value_gift_aid_submission']['fields'][$field]['default'] = true;
            }
        }
    }

    function select( ) {
        $select = array( );

        $this->_columnHeaders = array( );
        foreach ( $this->_columns as $tableName => $table ) {
            if ( array_key_exists('fields', $table) ) {
                foreach ( $table['fields'] as $fieldName => $field ) {
                    if ( CRM_Utils_Array::value( 'required', $field ) ||
                         CRM_Utils_Array::value( $fieldName, $this->_params['fields'] ) ) {
                        if ( $tableName == 'civicrm_address' ) {
                            $this->_addressField = true;
                        } else if ( $tableName == 'civicrm_email' ) {
                            $this->_emailField = true;
                        }

                        // only include statistics columns if set
                        if ( CRM_Utils_Array::value('statistics', $field) ) {
                            foreach ( $field['statistics'] as $stat => $label ) {
                                switch (strtolower($stat)) {
                                case 'sum':
                                    $select[] = "SUM({$field['dbAlias']}) as {$tableName}_{$fieldName}_{$stat}";
                                    $this->_columnHeaders["{$tableName}_{$fieldName}_{$stat}"]['title'] = $label;
                                    $this->_columnHeaders["{$tableName}_{$fieldName}_{$stat}"]['type']  =
                                        $field['type'];
                                    $this->_statFields[] = "{$tableName}_{$fieldName}_{$stat}";
                                    break;
                                case 'count':
                                    $select[] = "COUNT({$field['dbAlias']}) as {$tableName}_{$fieldName}_{$stat}";
                                    $this->_columnHeaders["{$tableName}_{$fieldName}_{$stat}"]['title'] = $label;
                                    $this->_statFields[] = "{$tableName}_{$fieldName}_{$stat}";
                                    break;
                                case 'avg':
                                    $select[] = "ROUND(AVG({$field['dbAlias']}),2) as {$tableName}_{$fieldName}_{$stat}";
                                    $this->_columnHeaders["{$tableName}_{$fieldName}_{$stat}"]['type']  =
                                        $field['type'];
                                    $this->_columnHeaders["{$tableName}_{$fieldName}_{$stat}"]['title'] = $label;
                                    $this->_statFields[] = "{$tableName}_{$fieldName}_{$stat}";
                                    break;
                                }
                            }

                        } else {
                            $select[] = "{$field['dbAlias']} as {$tableName}_{$fieldName}";
                            $this->_columnHeaders["{$tableName}_{$fieldName}"]['title'] = $field['title'];
                            $this->_columnHeaders["{$tableName}_{$fieldName}"]['type']  = CRM_Utils_Array::value( 'type', $field );
                        }
                    }
                }
            }
        }

        $this->_select = "SELECT " . implode( ', ', $select ) . " ";
    }

    function from( ) {
        $this->_from = "
          FROM civicrm_entity_batch {$this->_aliases['civicrm_entity_batch']}
          INNER JOIN civicrm_contribution {$this->_aliases['civicrm_contribution']}
                  ON {$this->_aliases['civicrm_entity_batch']}.entity_table = 'civicrm_contribution' AND
                     {$this->_aliases['civicrm_entity_batch']}.entity_id = {$this->_aliases['civicrm_contribution']}.id

          LEFT JOIN civicrm_address {$this->_aliases['civicrm_address']}
          ON ({$this->_aliases['civicrm_contribution']}.contact_id = {$this->_aliases['civicrm_address']}.contact_id
             AND {$this->_aliases['civicrm_address']}.is_primary = 1 )";
        }

    function where( ) {
        parent::where( );

        if ( empty($this->_where) ) {
            $this->_where = "WHERE value_gift_aid_submission_civireport.amount IS NOT NULL";
        } else {
            $this->_where .= " AND value_gift_aid_submission_civireport.amount IS NOT NULL";
        }
    }

  function statistics( &$rows ) {
        $statistics = parent::statistics( $rows );

        $select = "
        SELECT SUM( value_gift_aid_submission_civireport.amount ) as amount,
               SUM( value_gift_aid_submission_civireport.gift_aid_amount ) as giftaid_amount";
        $sql = "{$select} {$this->_from} {$this->_where}";
        $dao = CRM_Core_DAO::executeQuery( $sql );

        if ( $dao->fetch( ) ) {
            $statistics['counts']['amount']    = array( 'value' => $dao->amount,
                                                        'title' => 'Total Amount',
                                                        'type'  => CRM_Utils_Type::T_MONEY );
            $statistics['counts']['giftaid']       = array( 'value' => $dao->giftaid_amount,
                                                        'title' => 'Total Gift Aid Amount',
                                                        'type'  => CRM_Utils_Type::T_MONEY );
        }
        //print_r ($config);exit;
        return $statistics;
    }

    function postProcess( ) {
        parent::postProcess( );
    }

    function alterDisplay( &$rows ) {
        // custom code to alter rows
        $checkList  = array();
        $entryFound = false;
        $display_flag = $prev_cid = $cid =  0;
        require_once 'CRM/Contact/DAO/Contact.php';
        foreach ( $rows as $rowNum => $row ) {
          // handle contribution status id
            if ( array_key_exists('civicrm_contribution_contact_id', $row) ) {
                if ( $value = $row['civicrm_contribution_contact_id'] ) {
                    $contact = new CRM_Contact_DAO_Contact( );
                    $contact->id = $value;
                    $contact->find(  true );
                    $rows[$rowNum]['civicrm_contribution_contact_id'] = $contact->display_name;
                    $url = CRM_Utils_System::url( "civicrm/contact/view"  ,
                                            'reset=1&cid=' . $value,
                                            $this->_absoluteUrl );
                    $rows[$rowNum]['civicrm_contribution_contact_id_link' ] = $url;
                    $rows[$rowNum]['civicrm_contribution_contact_id_hover'] =
                        ts("View Contact Summary for this Contact.");
                }
                $entryFound = true;
            }


            // skip looking further in rows, if first row itself doesn't
            // have the column we need
            if ( !$entryFound ) {
                break;
            }
            $lastKey = $rowNum;
        }
    }

}


