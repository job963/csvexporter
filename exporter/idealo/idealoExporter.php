<?php

/**
 * This file is part of csvexporter Module for OXID eShop CE/PE/EE.
 *
 * csvexporter is free software: you can redistribute it and/or modify
 * it under the terms of the MIT License.
 *
 * @link http://marmalade.de
 * @copyright (C) Joachim Barthel 2014
 *
 */

//Set some helpful power ;-)
error_reporting(E_ALL);
set_time_limit(0);
ini_set("memory_limit", "1024M");

include '../../core/marmCsvExporter.php';

class marmIdealoExporter extends marmCsvExporter
{
    /**
     * Configuration
     */
    protected $_config = array(
        'export_parents'                => 0,               // Should parents be shown in file (not available !!!)
        'filename'                      => '../idealo.txt', // Export filename relative to this file
        'limit'                         => 500,             // limit for export (not available !!!)
        'debug'                         => false,           // enable / disable debug-output
        'silent'                        => false,           // enable / disable regular messages
        'header'                        => true,            // enable / disable headerline
        'langid'                        => 0,               // LanguageId for which you want to export
        'shippingcost'                  => array(                               // shipping cost categories
                                            array('from' => 0 , 'cost' => 4.9),
                                            array('from' => 100 , 'cost' => 0)
                                            ),      
        'shippingcost_at'                  => array(                            //shipping cost categories
                                            array('from' => 0 ,  'cost' => 14.90)
                                            ),
        'usedeliverytimes'              => false,                               //use delivery times from database (not supported yet)
        'deliverytimeunits'             => array(
                                            'DAY' => 'Werktage',
                                            'WEEK' => 'Wochen',
                                            'MONTH' => 'Monate'
                                            ),
        'deliverytime'                  => '1-3 Werktage',
        'deliverytime_at'               => '3-5 Werktage',
        'productLinkPrefix'             => '/index.php?cl=details&anid=',       //standard product url prefix
        //'idealoProductLinkParameters' => 'utm_source=idealo&utm_medium=csvex&utm_campaign=idealo', //idealo parameters for product        
        'idealoProductLinkParameters'   => '&pk_campaign=idealo&pk_kwd=',       // Piwik Campaign, Keyword = dynamic SEO Name
        'imageurl'                      => '/out/pictures/master/product/1/', 	//standard image url path
        'inStock'                       => 'auf Lager',                         //product in stock description
        'outOfStock'                    => 'Nicht auf Lager',                   //product out of stock description       
        'condition'                     => 'neu',                               //condition always new product
        'cutFirstPosArticlenumber'      => 0,                                   // cut the first n position from the article number
        'generalVat'                    => 19,                                  // general vat value for net prices
        'netPrices'                     => false,                               // net prices true/false
        'categoryPathSeparator'         => '>');                                // category path separator
	

    protected $_entry = array(
        'header'    => "Artikelnummer;EAN/GTIN;Herstellerartikelnummer;Herstellername;Produktname;Produktgruppe;Preis (Brutto);Grundpreis;Lieferzeit national;Lieferzeit Österreich;ProduktURL;BildURL_1;Versandkosten;Versandkosten Österreich;Produktbeschreibung",
        'fields'    => '#oxartnum#|#oxdistean#/#oxean#|#mpn#|#brand#|#oxtitle#+#oxvarselect#|#categoryPath#|#oxprice#|#basepricetext#|#deliverytime#|#deliverytime_at#|#seo_url_parent#|#imagelink#|#shippingcost#|#shippingcost_at#|#oxlongdesc#| ',
        'separator' => ';'
    );
    
    /**
     * Idealo specific
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
        
        if (isset($this->_config['idealoProductLinkParameters']))
        {
            $pos = strpos($sUrl, '?');
            
            if ($pos === false)
            {
                $sUrl .= '?'.$this->_config['idealoProductLinkParameters'];
            }
            else
            {
                $sUrl .= '&'.$this->_config['idealoProductLinkParameters'];
            }
            
            $sSeoName = basename(parent::getSeoUrl(),'.html');
            if ( strpos($sSeoName,'anid') !== FALSE ) {          //product id found
                $sSeoName = basename(parent::getSeoUrl(TRUE),'.html');
            }
            $sUrl .= $sSeoName;
        }
        
        return $sUrl;
    }    
    
}
$marmIdealoExporter = new marmIdealoExporter();
$marmIdealoExporter->start_export();