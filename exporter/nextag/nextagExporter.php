<?php

/**
 * This file is part of csvexporter Module for OXID eShop CE/PE/EE.
 *
 * csvexporter is free software: you can redistribute it and/or modify
 * it under the terms of the MIT License.
 *
 *
 * @link http://marmalade.de
 * @copyright (C) Joachim Barthel 2014
 */

//Set some helpful power ;-)
error_reporting(E_ALL);
set_time_limit(0);
ini_set("memory_limit", "1024M");

include '../../core/marmCsvExporter.php';

class jxNextagExporter extends marmCsvExporter
{
    /**
     * Configuration
     */
    protected $_config = array(
        'export_parents'                => 0,               // Should parents be shown in file (not available !!!)
        'filename'                      => '../../../../../export/nextag.txt',  // Export filename relative to this file
//        'filename'                      => '../nextag.txt', // Export filename relative to this file (for local test)
        'limit'                         => 500,             // limit for export (not available !!!)
        'debug'                         => false,           // enable / disable debug-output
        'silent'                        => false,            // enable / disable regular messages
        'header'                        => true,            // enable / disable headerline
        'langid'                        => 0,               // LanguageId for which you want to export
        'shippingcost'                  => array(           // shipping cost categories
                                            array('from' => 0 , 'cost' => 4.9),
                                            array('from' => 100 , 'cost' => 0)
                                            ),      
        'productLinkPrefix'             => '/index.php?cl=details&anid=',       //standard product url prefix
        //'nextagProductLinkParameters' => 'utm_source=nextag&utm_medium=csvex&utm_campaign=nextag', //nextag parameters for product        
        'nextagProductLinkParameters' => '&pk_campaign=nextag&pk_kwd=', // Piwik Campaign, Keyword = dynamic
        'imageurl'                      => '/out/pictures/master/product/1/', 	//standard image url path
        'inStock'                       => 'auf Lager',                         //product in stock description
        'outOfStock'                    => 'Nicht auf Lager',                   //product out of stock description       
        'condition'                     => 'neu',                               //condition always new product
        'cutFirstPosArticlenumber'      => 0,                                   // cut the first n position from the article number
        'generalVat'                    => 19,                                  // general vat value for net prices
        'netPrices'                     => false,                                // net prices true/false
        'categoryPathSeparator'         => '>');                                // category path separator
	

    protected $_entry = array(
        //'header'    => "Bezeichnung;Hersteller;Herst.Nr.;Preis;VerfÃŒgbarkeit;VersandDE;EAN;Deeplink;Artikelnummer;Beschreibung;Kategorie;Bildlink",
        //'fields'    => '#brand#+#oxtitle#+#oxvarselect#|#brand#|#mpn#|#oxprice#|#availability#|#shippingcost#|#oxdistean#|#seo_url_parent#|#oxid#/#mpn#/#ERROR#|#oxshortdesc#/#oxlongdesc#|#categoryPath#|#imagelink#',
        'header'    => "Hersteller;Hersteller-Art.-Nr.;Produktbezeichnung;Produktbeschreibung;Klick-Out-URL;Preis;Händler-Produktkat.;Nextag-Produktkat.;Bild-URL;Standardversand;Lagerstatus;Produktzustand;Marketingbotschaft;Gewicht;Kosten-pro-Klick;EAN;Lieferanten-ID;MUZE ID;ISBN",
        'fields'    => '#brand#|#mpn#|#oxtitle#+#oxvarselect#|#oxlongdesc#|#seo_url#|#oxprice#|#categoryPath#| |#imagelink#| |#imagelink#|#shippingcost#|#availability#|#condition#| | | |#oxean#| | | ',
        'separator' => ','
    );
    
    /**
     * Nextag specific
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
        
        if (isset($this->_config['nextagProductLinkParameters']))
        {
            $pos = strpos($sUrl, '?');
            
            if ($pos === false)
            {
                $sUrl .= '?'.$this->_config['nextagProductLinkParameters'];
            }
            else
            {
                $sUrl .= '&'.$this->_config['nextagProductLinkParameters'];
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
$jxNextagExporter = new jxNextagExporter();
$jxNextagExporter->start_export();