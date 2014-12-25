<?php

/**
 * This file is part of csvexporter Module for OXID eShop CE/PE/EE.
 *
 * csvexporter is free software: you can redistribute it and/or modify
 * it under the terms of the MIT License.
 *
 * @link http://marmalade.de
 * @copyright (C) marmalade.de 2014
 *
 */

//Set some helpful power ;-)
error_reporting(E_ALL);
set_time_limit(0);
ini_set("memory_limit", "1024M");

include '../../core/marmCsvExporter.php';

class jxAmazonExporter extends marmCsvExporter
{
    /**
     * Configuration
     */
    protected $_config = array(
        'export_parents'                => 0,               // Should parents be shown in file !!!not available
        'filename'                      => '../../../../../export/amazon.txt', // Export filename relative to this file
//        'filename'                      => '../google.txt', // Export filename relative to this file (for local test)
        'limit'                         => 500,             // limit for export !!!not available
        'debug'                         => false,           // enable / disable debug-output
        'silent'                        => false,            // enable / disable regular messages
        'header'                        => true,            // enable / disable headerline
        'langid'                        => 0,               // LanguageId for which you want to export
        'shippingcost'                  => array(           //shipping cost categories
                                            array('from' => 0 ,  'cost' => 4.90),
                                            array('from' => 100 , 'cost' => 0.00)
                                            ),
        'shippingcost_at'                  => array(           //shipping cost categories
                                            array('from' => 0 ,  'cost' => 14.90)
                                            ),
        'productLinkPrefix'             => '/index.php?cl=details&anid=',       //standard product url prefix
        'amazonProductLinkParameters'   => 'pk_campaign=Amazon&pk_kwd=', //google parameters for product        
        'imageurl'                      => '/out/pictures/master/product/1/', //standard image url path
        'condition'                     => 'neu',                               //condition always new product
        'inStock'                       => 'auf Lager',                         //product in stock description
        'outOfStock'                    => 'nicht auf Lager',                   //product out of stock description       
        'cutFirstPosArticlenumber'      => 0,                                   // cut the first n position from the article number
        'generalVat'                    => 19,                                  // general vat value for net prices
        'netPrices'                     => false,                                // net prices true/false
        'categoryPathSeparator'         => '>');                                // category path separator

    protected $_entry = array(
        //'header'    => "ID;Titel;Beschreibung;Produkttyp;Google Produktkategorie;Link;Bildlink;Zustand;Verfügbarkeit;Preis;Marke;GTIN;MPN;Versand",
        //'header'    => "ID;Titel;Beschreibung;Google Produktkategorie;Produkttyp;Link;Bildlink;Zustand;Verfügbarkeit;Preis;Marke;GTIN;MPN;Kennzeichnung existiert;Versand;Grundpreis Maß;Grundpreis Einheitsmaß",
        'header'    => "c:product_ads_product_type;id;title;description;google_product_category;product_type;link;image_link;condition;availability;price;brand;gtin;mpn;identifier_exists;shipping;unit_pricing_measure;unit_pricing_base_measure",
        'fields'    => '#amazon_category#|#oxartnum#|#oxtitle#+#oxvarselect#|#oxlongdesc#|#google_categoryPath#|#categoryPath#|#seo_url_parent#|#imagelink#|#condition#|#availability#|#oxprice#|#brand#|#oxdistean#/#oxean#|#mpn#|#identifierexists#|#shippingcost#|#unitpricingmeasure#|#unitpricingbasemeasure#',
        //'fields'    => '#oxean#/#mpn#/#ERROR#|#oxtitle#+#oxvarselect#|#oxshortdesc#/#oxlongdesc#|#categoryPath#|#google_categoryPath#|#seo_url_parent#|#imagelink#|#condition#|#availability#|#oxprice#|#brand#|#oxdistean#/#oxean#|#mpn#|#shippingcost#',
        'separator' => '~'
    );
    
    /**
     * google specific
     * Calls the method from marmCsvExporter and replaces the value
     * @return string
     */
    public function getShippingcost($sCountryId = 'de')
    {
        //$shippingcost = parent::getShippingcost($sCountryId);
        //$shippingcost = 'DE:::'.$shippingcost;
        //$shippingcost = parent::getShippingcost('de');
        $shippingcost = 'DE:::'.parent::getShippingcost('de');
        $shippingcost .= ',';
        //$shippingcost .= parent::getShippingcost('at');
        $shippingcost .= 'AT:::'.parent::getShippingcost('at');
        
        return $shippingcost;
    }

    /**
     * amazon specific
     * Calls the method from marmCsvExporter and replaces the value
     * 
     * get seo url
     * 
     * @param boolean only parent
     * @return string
     */ 
    public function getSeoUrl($onlyParent = false)
    {
        $sUrl = parent::getSeoUrl($onlyParent);
        
        if (isset($this->_config['amazonProductLinkParameters']))
        {
            $pos = strpos($sUrl, '?');
            
            if ($pos === false)
            {
                $sUrl .= '?'.$this->_config['amazonProductLinkParameters'];
            }
            else
            {
                $sUrl .= '&'.$this->_config['amazonProductLinkParameters'];
            }
            $sSeoName = basename(parent::getSeoUrl(),'.html');
            //echo '  ***'.$sSeoName.'***  ';
            if ( strpos($sSeoName,'anid') !== FALSE ) //found
            {
                $sSeoName = basename(parent::getSeoUrl(TRUE),'.html');
            }
            $sUrl .= $sSeoName;
        }
        
        return $sUrl;
    }    
    
}
$jxAmazonExporter = new jxAmazonExporter();
$jxAmazonExporter->start_export();