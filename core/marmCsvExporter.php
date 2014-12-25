<?php

/**
 * This file is part of csvexporter Module for OXID eShop CE/PE/EE.
 *
 * csvexporter is free software: you can redistribute it and/or modify
 * it under the terms of the MIT License.
 *
 *
 * @link http://marmalade.de
 * @copyright (C) marmalade.de 2014
 */

class marmCsvExporter
{
    protected $manufacturersTitle = array();
    protected $categoriesTitle = array();
    protected $contentPage = array();
    protected $unitTranslation = array();
    protected $googleCategoriesTitle = array();
    protected $entryFields = array();
    protected $realInventory = array();
    
    /**
     * Loads the shop configuration
     * sets up the DB connection
     * Opens the filehandler and prepare the file with the header if requested
     */
    public function __construct()
    {
        //include Shop Configuration
        require_once '../../../../../config.inc.php';

        //setup connection to database
        $db_connection = @mysql_connect($this->dbHost, $this->dbUser, $this->dbPwd);
        @mysql_select_db($this->dbName, $db_connection);
        
        if($this->iUtfMode == 1)
        {
            mysql_query("SET NAMES 'utf8'");
        }
        
        // check sShopURL from config.inc.php (remove slash if is given)
        if (substr($this->sShopURL, -1) == '/')
        {
            $this->sShopURL = substr($this->sShopURL,0, -1);
        }
        
        $this->initFile();
        $this->writeFileHeader();
        $this->cachingEntryFields();
    }
    
    /**
     * Destructor closes the filehandler
     */
    public function __destruct() {
        fclose($this->filehandler);
    }
    
    /**
     * reads the config and triggers the export
     */
    public function start_export()
    {
        if(!$this->_config['silent'])
        {
            echo "<pre>Export gestartet!\n";
        }
        
        $this->cacheCategoriesTitles();
        $this->cacheGoogleCategoriesTitles();
        //$this->cacheInventory();
        $this->setUnitTranslations();
        $this->cacheManufacturesTitles();
        $this->cacheContentPages();     // for [{oxcontent... in long description
        $parents = $this->getParentProducts();
        $this->handleParents($parents);
        
        if(!$this->_config['silent'])
        {
            echo "Export beendet!\n</pre>";
        }
    }

    /**
     * Walks through the array for all parent products.
     * Loads childrens if requested and trigers the writing
     * @param array $parents parent products
     */
    
    //check if the product is a parent. If so print the product. Else for that parent
    //retrieve the children and print them.
    public function handleParents($parents)
    {
        foreach($parents as $parent)
        {
            //check if is single (OXVARNAME is filled for parent with childs)
            // -- jbl -- if($parent['OXVARNAME'] == '') --- wrong this is optionally
            if($parent['OXVARCOUNT'] == 0)
            {
                $this->writeEntryToFile($parent);
            }
            else
            {
                $this->tempParent = $parent;
                $children = $this->getChildren($parent['OXID']);
                
                // if OXVARNAME is filled but no childs exists
                if(!empty($children))
                {
                    foreach($children as $child)
                    {
                       $this->writeEntryToFile($child);
                    }
                }
            }
            if (isset($this->tempParent)) unset($this->tempParent); 
            if (isset($this->tempMainCategoryId)) unset($this->tempMainCategoryId);
        }		
    }
    
    /**
     * caching explodes
     * fill $this->entryFields
     */
    public function cachingEntryFields()
    {
        $result = array();
        $col = 0;
        $conc = 0;
        $fb = 0;
        
        $columns = explode( '|', $this->_entry['fields'] ); //split the header
        foreach($columns as $column)
        {
            $concatenations = explode('+', $column);
            
            foreach($concatenations as $concatenate)
            {
                $varFallbacks = explode('/', $concatenate);
                foreach($varFallbacks as $marker)
                {
                 $result[$col][$conc][$fb] = $marker;
                 $fb++;
                }
             
            $conc++;
            }
        $col++;
        }
        $this->entryFields = $result;
    }
    
    /**
     * collect the data for a marker
     * @param string $marker
     * @return string that contains data;
     */
    public function getDataByMarker($marker)
    {
        switch($marker)
        {
            case '#oxid#':
                return $this->tempProduct['OXID'];
            case '#oxshortdesc#':
		return $this->getShortDescription();
            case '#oxlongdesc#':
                return $this->exchOxContent($this->getLongDescription());
            case '#categoryPath#':
                return $this->getCategoryPath();
            case '#amazon_category#':
                return 'PET_SUPPLIES';
            case '#google_categoryPath#':
                return $this->getGoogleCategoryPath();
            case '#condition#':
                return $this->getCondition();
            case '#imagelink#':
                return $this->getImageLink();
            case '#availability#':
                return $this->getProductAvailability();
            case '#oxprice#':
                return $this->getProductPrice();
            case '#baseprice#':
                return $this->getProductBasePrice();
                /*if ($this->getProductUnitQuantity() != 0) {
                    //echo '***'.$this->getProductUnitQuantity().'***';
                    if ($this->tempProduct['OXPARENTID'] == '')             // not a variant
                        return round($this->getProductPrice()/$this->getProductUnitQuantity(),2);
                    elseif ($this->tempProduct['OXUNITQUANTITY'] == 0)      // variant without own unit price
                        return round($this->getProductPrice()/$this->getProductUnitQuantity(),2);
                    else                                                    // variant with an own unit price
                        return round($this->getProductPrice()/$this->getProductUnitQuantity(),2);
                }
                else
                    return 0;*/
            case '#basepricetext#':
                return $this->getProductBasePriceText();
            case '#unitpricingmeasure#':
                if ( empty($this->tempProduct['OXUNITNAME']) )
                    break;
                $amount = $this->tempProduct['OXUNITQUANTITY'];
                if (array_key_exists($this->tempProduct['OXUNITNAME'], $this->unitTranslation))
                    $unitMeasure = $this->unitTranslation[$this->tempProduct['OXUNITNAME']];
                else {
                    $returnValue = preg_match('/[0-9]*/', $this->tempProduct['OXUNITNAME'], $matches, PREG_OFFSET_CAPTURE);
                    if ((float)$matches[0][0] == 0)
                        $baseamount = 1;
                    else
                        $baseamount = $matches[0][0];

                    $amount = $this->tempProduct['OXUNITQUANTITY'] * $baseamount;
                    if (empty($amount))
                        $amount = 1;

                    $returnValue = preg_match('/[^0-9 ].*/', $this->tempProduct['OXUNITNAME'], $matches, PREG_OFFSET_CAPTURE);
                    $unitMeasure = $matches[0][0];
                }
                return $amount . ' ' . $unitMeasure;
            case '#unitpricingbasemeasure#':
                if ( empty($this->tempProduct['OXUNITNAME']) )
                    break;
                if (array_key_exists($this->tempProduct['OXUNITNAME'], $this->unitTranslation))
                    $baseMeasure = '1 ' . $this->unitTranslation[$this->tempProduct['OXUNITNAME']];
                else {
                    $returnValue = preg_match('/[0-9]*/', $this->tempProduct['OXUNITNAME'], $matches, PREG_OFFSET_CAPTURE);
                    if ((float)$matches[0][0] == 0)
                        $baseMeasure = 1 . ' ' . $this->tempProduct['OXUNITNAME'];
                    else
                        $baseMeasure = $this->tempProduct['OXUNITNAME'];
                }
                return $baseMeasure;
            case '#brand#':
                return $this->getManufacturesTitle();
            case '#oxean#':
                return $this->tempProduct['OXEAN'];
            case '#oxdistean#':
                return $this->tempProduct['OXDISTEAN'];                
            case '#mpn#':
                return $this->getMpn();
            case '#identifierexists#':
                if ( ($this->getManufacturesTitle() == '') && (($this->getMpn() == '')  || ($this->tempProduct['OXEAN'] == '')) )
                    $sVal = 'FALSE';
                else
                    $sVal = '';
                return $sVal;
            case '#oxtitle#':
                return $this->getTitle();
            case '#oxvarselect#':
                return $this->getVarName();
            case '#shippingcost#':
                return $this->getShippingcost();
            case '#shippingcost_at#':
                return $this->getShippingcost('at');
            case '#deliverytime#':
                return $this->getDeliverytime();
            case '#deliverytime_at#':
                return $this->getDeliverytime('at');
            case '#seo_url_parent#':
                return $this->getSeoUrl(true);
            case '#seo_url#':
                return $this->getSeoUrl();
            case '#oxartnum#':
                return $this->tempProduct['OXARTNUM'];
            case '#oxtags#':
                return $this->getTags();
            case '#oxsearchkeys#':
                return $this->tempProduct['OXSEARCHKEYS'];
            case '#uvp#':
                return $this->getUVP();
            case '#ERROR#':
                return "!FEHLER!";
            default:
                return '';
        }
    }
    
    /**
     * replace the values from the template and write article to file
     * @param string oxid
     * @return void
     */
    public function writeEntryToFile($oxarticle)
    {
        $col=0;
        $conc=0;
        $fb=0;
        $this->tempProduct = $oxarticle; //to get values for the markers
        $dataarray = $this->entryFields;
        foreach($dataarray as $col)
        {
            $replace = array();
            foreach($col as $conc)
            {
                foreach($conc as $marker)
                {
                    $data = $this->getDataByMarker($marker);
                    if($data != '' )
                    {
                        break; //if data found OK stop reading the markers.
                    }
                    $fb++;
                }
                if($data == '')
                {
                    $data = ' ';
                }
                // replace csv separator, html tags, tabs and wordwraps from data
                /*$replacedata = array($this->_entry['separator'],"\n","\r","\t","\r\n");
                $data = str_replace($replacedata,'', strip_tags($data));*/
                $replacedata = array("\n","\r","\t","\r\n");
                $data = strip_tags($data);
                $data = str_replace($this->_entry['separator'],'', $data);
                $data = str_replace($replacedata,' ', $data);
                $data = preg_replace("/[[:blank:]]+/"," ",$data);
                $data = trim($data);
                
                $replace[] = $data;
            }
            // if the column must be saved as quoted string
            if(isset($this->_config['quote']))
            {
                $quote = '"';
            }
            else 
            {
                $quote = '';
            }
            $concatenated[] = $quote . implode(" ", $replace) . $quote; //concatenare data on the column and leave a space between them
        }
    
        $entry = implode( $this->_entry['separator'], $concatenated );
        
        fputs($this->filehandler,$entry."\n");
        
        if(!$this->_config['silent'])
        {
            echo $entry . "<br>";
        }
    }    

    /**
     * get the MainCategoryId
     * @param string oxid
     * @return string
     */    
    public function getMainCategoryId($oxid)
    {
        if (isset($this->tempMainCategoryId[$oxid]))
        {
            return $this->tempMainCategoryId[$oxid];
        }
        
        /* --jbl-- $query="select oxcatnid
                from oxobject2category
                where oxobjectid = '".$oxid."'
                order by oxtime
                limit 1;";*/
        $query="SELECT o2c.oxcatnid
                FROM oxobject2category o2c, oxcategories c 
                WHERE o2c.oxobjectid = '".$oxid."' 
                    AND o2c.oxcatnid = c.oxid 
                    AND c.oxactive = 1 
                    AND c.oxhidden = 0 
                ORDER BY o2c.oxtime
                LIMIT 1;";
        
        $rs = mysql_query($query);
        if ($rs)
        {
            while($row = mysql_fetch_array($rs))
            {
                // caching query
                $this->tempMainCategoryId[$oxid] = $row['oxcatnid'];
                return $row['oxcatnid'];
            }	
        }
        // caching query
        $this->tempMainCategoryId[$oxid] = '';
        return '';
    }

    /**
     * get the MainCategoryId
     * @param string oxid
     * @return string
     */    
    public function getAllCategoryIds($oxid)
    {
        /*if (isset($this->tempMainCategoryId[$oxid]))
        {
            return $this->tempMainCategoryId[$oxid];
        }*/
        
        /* --jbl-- $query="select oxcatnid
                from oxobject2category
                where oxobjectid = '".$oxid."'
                order by oxtime
                limit 1;";*/
        $query="SELECT o2c.oxcatnid
                FROM oxobject2category o2c, oxcategories c 
                WHERE o2c.oxobjectid = '".$oxid."' 
                    AND o2c.oxcatnid = c.oxid 
                    AND c.oxactive = 1 
                    AND c.oxhidden = 0 
                ORDER BY o2c.oxtime ";
        
        $rs = mysql_query($query);
        $aCatIds = array();
        if ($rs)
        {
            while($row = mysql_fetch_array($rs))
            {
                // caching query
                /*$this->tempMainCategoryId[$oxid] = $row['oxcatnid'];
                return $row['oxcatnid'];*/
                array_push( $aCatIds, $row['oxcatnid'] );
            }	
        }
        // caching query
        /*$this->tempMainCategoryId[$oxid] = '';
        return '';*/
        return $aCatIds;
    }
    
    /**
     * get the category path devided by " > "
     * @return string
     */
    public function getCategoryPath()
    {		
        $path = array();
        
        // check tempProduct (it can be a parent or child product)
        $categoryId = $this->getMainCategoryId($this->tempProduct['OXID']);
        if ($categoryId == '')
        {
            // if child (tempParent is set) get the parent
            if (isset($this->tempParent['OXID']) && !empty($this->tempParent['OXID']))
            {
                $categoryId = $this->getMainCategoryId($this->tempParent['OXID']);
            }
        }
        
        // if parent or child has a category
        if($categoryId != '')
        {
            if (isset($this->_config['categoryPathSeparator']))
            {
                $sep = $this->_config['categoryPathSeparator'];
            }
            else
            {
                $sep = '>';
            }
                
            while($categoryId != 'oxrootid') 
            {
                array_unshift($path, $this->categoriesTitle[$categoryId]['title']);
                $categoryId = $this->categoriesTitle[$categoryId]['parentid'];
            }
            return implode( ' '.$sep.' ', $path );
        }
        
        return '';
    }
    
    /**
     * get the google category path
     * @return string
     */
    public function getGoogleCategoryPath()
    {
        // check if cache was successful
        if(!empty($this->googleCategoriesTitle))
        {
            // check tempProduct (it can be a parent or child product)
            $categoryId = $this->getMainCategoryId($this->tempProduct['OXID']);
            if ($categoryId == '')
            {
                // if child (tempParent is set) get the parent
                if (isset($this->tempParent['OXID']) && !empty($this->tempParent['OXID']))
                {
                    $categoryId = $this->getMainCategoryId($this->tempParent['OXID']);
                }
            }

            // if parent or child has a category
            if($categoryId != '')
            {
                return $this->googleCategoriesTitle[$categoryId]['title'];
            }
        }
        
        return '';
    }
    
    /**
     * caching google categories titles
     * fill $this->googleCategoriesTitle
     */
    public function cacheGoogleCategoriesTitles()
    {
        $googleCategories = array();
        $query = "SELECT OXID, JXGOOGLETAXONOMY FROM oxcategories";
        $rs = mysql_query($query);
        if ($rs)
        {
            while($row = mysql_fetch_array($rs))
            {
                //$googleCategories[$row[0]] = array('title' => base64_decode($row[1]));
                $googleCategories[$row[0]] = array('title' => $row[1]);
            }
        }
        $this->googleCategoriesTitle = $googleCategories;
    }
    
    /**
     * caching google categories titles
     * fill $this->googleCategoriesTitle
     */
    public function cacheInventory()
    {
        $googleCategories = array();
        $query = "SELECT JXARTID, JXINSTOCK FROM jxinvarticles";
        $rs = mysql_query($query);
        if ($rs)
        {
            while($row = mysql_fetch_array($rs))
            {
                //$googleCategories[$row[0]] = array('title' => base64_decode($row[1]));
                $googleCategories[$row[0]] = array('title' => $row[1]);
            }
        }
        $this->googleCategoriesTitle = $googleCategories;
    }
	
    /**
     * Get the shipping cost
     * @return string
     */
    public function getShippingcost($sCountryId = 'de')
    {
        if ($sCountryId == 'de')
        {
            if( isset($this->_config['shippingcost']))
            {
                $shippingcost = $this->_config['shippingcost'];
            }
        }
        else
        {
            if( isset($this->_config['shippingcost_'.$sCountryId]))
            {
                $shippingcost = $this->_config['shippingcost_'.$sCountryId];
            }
        }
        
        if (!isset($shippingcost)) {
            return '';
        }
        
        $productPrice = $this->getProductPrice();
        $arraymarker = 0;
        for ($i = 0; $i < count($shippingcost); $i++)
        {
            if($productPrice >= $shippingcost[$i]['from'])
            {
                $arraymarker = $i;
            }
        }
        
        return (string) $shippingcost[$arraymarker]['cost'];
    }
	
    /**
     * Get the shipping cost
     * @return string
     */
    public function getDeliverytime($sCountryId = 'de')
    {
        if ($sCountryId == 'de')
        {
            if( isset($this->_config['deliverytime']))
            {
                $deliverytime = $this->_config['deliverytime'];
            }
        }
        else
        {
            if( isset($this->_config['deliverytime_'.$sCountryId]))
            {
                $deliverytime = $this->_config['deliverytime_'.$sCountryId];
            }
        }
        
        if (!isset($deliverytime)) {
            return '';
        }

        return (string) $deliverytime;
        //---return $this->tempProduct['OXMINDELTIME'] . ' - ' .$this->tempProduct['OXMAXDELTIME'] . ' ' . $this->_config['deliverytimeunits'][$this->tempProduct['OXDELTIMEUNIT']];
    }
	
    /**
     * Get an Attribute
     * @return string
     */
    public function getAttribute($attr)
    {
        if (isset($this->_config['attributes'][$attr]))
        {
            $attrTitle = $this->_config['attributes'][$attr];
                
            $query  = "SELECT oa.OXVALUE
                       FROM oxobject2attribute oa, oxattribute at
                       WHERE oa.OXATTRID = at.OXID AND
                       oa.OXOBJECTID = '".$this->tempProduct['OXID']."' AND
                       at.OXTITLE = '".$attrTitle."' LIMIT 1";

            $rs = mysql_query($query);
         
            if ($rs)
            {
                while($row = mysql_fetch_array($rs))
                {
                    if ($row['OXVALUE'] != '')
                    {
                        return $row['OXVALUE'];
                    }
                }	
            }
            
            // check parent
            if(isset($this->tempParent['OXID']))
            {
                $query  = "SELECT oa.OXVALUE
                           FROM oxobject2attribute oa, oxattribute at
                           WHERE oa.OXATTRID = at.OXID AND
                           oa.OXOBJECTID = '".$this->tempParent['OXID']."' AND
                           at.OXTITLE = '".$attrTitle."' LIMIT 1";                
    
                $rs = mysql_query($query);
                if ($rs)
                {
                    while($row = mysql_fetch_array($rs))
                    {    
                        if ($row['OXVALUE'] != '')
                        {
                            return $row['OXVALUE'];
                        }                   
                    }	
                }                
            }
        }

        return '';
    }
    
    /**
     * Writes the header to the file if requested
     */
    public function writeFileHeader()
    {
        if($this->_config['header'])
        {
            $data = $this->_entry['header'];
            $replace = explode(';',$data);
            $newdata = implode( $this->_entry['separator'], $replace );
            
            if($this->iUtfMode == 0)
            {
                $newdata = utf8_decode($newdata);
            }            
            fputs($this->filehandler, $newdata."\n");
        }
    }
    
    /**
     * Check if file exists. If so, remove it
     * Open filehandler with empty file
     */
    public function initFile() 
    {
        if ( file_exists($this->_config['filename']) ) 
        {
            unlink($this->_config['filename']);
        }
        $this->filehandler = fopen($this->_config['filename'], "w+");
    }
    
    /**
     * Selects the basic entries (oxarticles + oxartextends) from the database
     * 
     * @return array with products
     */
    public function getParentProducts()
    {
        $parentProducts = null;
        
        /* the order of oxartex.*,oxart.* is important because the left join and the oxid that is on both tables
         * the second oxid will be NULL and so overwritten if we dont do that
         */
        $query ="SELECT oxartex.*,oxart.*
                 FROM oxarticles oxart LEFT JOIN oxartextends oxartex ON (oxart.oxid = oxartex.oxid)
                 WHERE oxart.oxactive = 1 AND
                 oxart.oxparentid = ''";
        
        $rs = mysql_query($query);
        if ($rs) 
        {
            while($row = mysql_fetch_array($rs))
            {
                $parentProducts[] = $row;
            }
        }
        if($this->_config['debug'])
        {
            echo count($parentProducts) . " Vaterprodukte gefunden.\n";
        }
        return $parentProducts;
    }
    
    /**
     * Selects the basic entries (oxarticles + oxartextends) from the database
     * 
     * @return array with products
     */
    public function getChildren($parentid)
    {
        $childrenProducts = null;
        
        /* the order of oxartex.*,oxart.* is important because the left join and the oxid that is on both tables
         * the second oxid will be NULL and so overwritten if we dont do that
         */
        $query ="SELECT oxartex.*,oxart.*
                 FROM oxarticles oxart LEFT JOIN oxartextends oxartex ON (oxart.oxid = oxartex.oxid)
                 WHERE oxart.oxactive = 1 AND
                 oxart.oxparentid = '".$parentid."'";
		
        $rs = mysql_query($query);
        if ($rs)
        {
            while($row = mysql_fetch_array($rs)) 
            {
                $childrenProducts[] = $row;
            }
        }
        if($this->_config['debug'])
        {
            echo count($childrenProducts) . " Kinderprodukte gefunden.\n";
        }
        return $childrenProducts;
    }
    
     /**
     * Replaces the placeholders of a single product
     * 
     * @return void
     */
    public function getCondition()
    {
        $condition = $this->_config['condition'];
        return $condition;   
    }
    
    /**
     * Replaces the placeholders of a single product
     * 
     * @return void
     */
    public function getImageLink()
    {        
        if(isset($this->tempProduct['OXPIC1']) && !empty($this->tempProduct['OXPIC1']))
        {
              $image = $this->tempProduct['OXPIC1'];
        }
        else if (isset($this->tempParent['OXPIC1']) && !empty($this->tempParent['OXPIC1']))
        {
              $image = $this->tempParent['OXPIC1'];
        }
        
        if (isset($image))
        {
            return $this->sShopURL.$this->_config['imageurl'].$image;
        }
        
        return '';
    }
    
    /**
     * Check if product in stock
     * 
     * @return type string
     */
    public function getProductAvailability(){ 
        $inStockDescription = utf8_decode($this->_config['inStock']);
        $outOfStockDescription = utf8_decode($this->_config['outOfStock']);
        if($this->tempProduct['OXSTOCK']>0)
        {
            return $inStockDescription;
        }
        return $outOfStockDescription;
    }
    
    /**
     * caching the manufactures titles
     * 
     * fill $this->manufacturersTitle
     */
    public function cacheManufacturesTitles()
    {
        $manufacturers = array();
        $query="SELECT oxmanufacturers.OXID, oxmanufacturers.OXTITLE FROM oxmanufacturers";
        $rs = mysql_query($query);
        if ($rs) 
        {
            while($row = mysql_fetch_array($rs)) 
            {
                $manufacturers[$row[0]] = $row[1];
            }
        }
        $this->manufacturersTitle = $manufacturers;
    }
    
    /**
     * caching the manufactures titles
     * 
     * fill $this->manufacturersTitle
     */
    public function cacheContentPages()
    {
        $contentpages = array();
        $query="SELECT oxcontents.OXLOADID, oxcontents.OXCONTENT FROM oxcontents";
        $rs = mysql_query($query);
        if ($rs) 
        {
            while($row = mysql_fetch_array($rs)) 
            {
                $contentpages[$row[0]] = $row[1];
            }
        }
        $this->contentPage = $contentpages;
    }
    
    /**
     * get manufactures title
     * 
     * @return string
     */
    public function getManufacturesTitle()
    {
        if(isset($this->tempProduct['OXMANUFACTURERID']) && !empty($this->tempProduct['OXMANUFACTURERID']))
        {
              $manufacturerId = $this->tempProduct['OXMANUFACTURERID'];
        }
        else if (isset($this->tempParent['OXMANUFACTURERID']) && !empty($this->tempParent['OXMANUFACTURERID']))
        {
              $manufacturerId = $this->tempParent['OXMANUFACTURERID'];
        }
        
        // if child or parent has a manufacturer
        if (isset($manufacturerId))
        {
            if (isset($this->manufacturersTitle[$manufacturerId]))
            {
                return $this->manufacturersTitle[$manufacturerId];
            }
        }
        
        return '';
    }
    
    /**
     * get content page text
     * 
     * @return string
     */
    public function getContentPageText($ident)
    {
        return $this->contentPage[$ident];
    }
      
    /**
     * caching the categories titles
     * 
     * fill $this->categoriesTitle
     */
    public function cacheCategoriesTitles()
    {
	$categories = array();
        $query="SELECT OXID, OXTITLE, OXPARENTID FROM oxcategories";
        $rs = mysql_query($query);
        if ($rs)
        {
            while($row = mysql_fetch_array($rs))
            {
                $categories[$row[0]] = array('title' => $row[1],'parentid' => $row[2]);
            }
        }
        $this->categoriesTitle = $categories;
     }
         
    /**
     * get short description
     * 
     * @return string
     */
    public function getShortDescription()
    {       
        if(isset($this->tempProduct['OXSHORTDESC']) && !empty($this->tempProduct['OXSHORTDESC']))
        {
            return $this->tempProduct['OXSHORTDESC'];
        }
        if (isset($this->tempParent['OXSHORTDESC']) && !empty($this->tempParent['OXSHORTDESC']))
        {
            return $this->tempParent['OXSHORTDESC'];
        }
        return '';
    }
    
    /**
     * get long description
     * 
     * @return string
     */
    public function getLongDescription()
    {        
        if(isset($this->tempProduct['OXLONGDESC']) && !empty($this->tempProduct['OXLONGDESC']))
        {
            return $this->tempProduct['OXLONGDESC'];
        }
        if (isset($this->tempParent['OXLONGDESC']) && !empty($this->tempParent['OXLONGDESC']))
        {
            return $this->tempParent['OXLONGDESC'];
        }
        return '';
    }
    
    /**
     * get tags
     * 
     * @return string
     */
    public function getTags()
    {        
        if(isset($this->tempProduct['OXTAGS']) && !empty($this->tempProduct['OXTAGS']))
        {
            return $this->tempProduct['OXTAGS'];
        }
        if (isset($this->tempParent['OXTAGS']) && !empty($this->tempParent['OXTAGS']))
        {
            return $this->tempParent['OXTAGS'];
        }
        return '';
    }    
    
    /**
     * get article number
     * 
     * @return string
     */
    public function getMpn()
    {
        if (isset($this->_config['cutFirstPosArticlenumber']))
        {
            $cutFirstPos = $this->_config['cutFirstPosArticlenumber'];
            
            if (!is_numeric($cutFirstPos) || $cutFirstPos < 0)
            {
                $cutFirstPos = 0;
            }
        }
        
        if(isset($this->tempProduct['OXARTNUM']) && !empty($this->tempProduct['OXARTNUM']))
        {
                return substr($this->tempProduct['OXARTNUM'], $cutFirstPos);
        }
        if (isset($this->tempParent['OXARTNUM']) && !empty($this->tempParent['OXARTNUM']))
        {
                return substr($this->tempParent['OXARTNUM'], $cutFirstPos);
        }
        return '';
    }
    
    /**
     * get title
     * 
     * @return string
     */
    public function getTitle()
    {
        if(isset($this->tempProduct['OXTITLE']) && !empty($this->tempProduct['OXTITLE']))
        {
            return $this->tempProduct['OXTITLE'];
        }
        if (isset($this->tempParent['OXTITLE']) && !empty($this->tempParent['OXTITLE']))
        {
            return $this->tempParent['OXTITLE'];
        }
        return '';
    }
    
    /**
     * get varname
     * 
     * @return string
     */
    public function getVarName()
    {
        if(isset($this->tempProduct['OXVARSELECT']) && !empty($this->tempProduct['OXVARSELECT']))
        {
            return $this->tempProduct['OXVARSELECT'];
        }
        return '';
    }
        
    /**
     * get seo url
     * 
     * @param boolean only parent
     * @return string
     */
    public function getSeoUrl($onlyParent = false)
    {
        $oxid = $this->tempProduct['OXID'];
        
        // if child but no childs allowed than take the parent oxid
        if($onlyParent and isset($this->tempParent['OXID']))
        {
            $oxid = $this->tempParent['OXID'];
        }
        
        //-- jbl -- $categoryId = $this->getMainCategoryId($oxid);
        $aCategoryIds = $this->getAllCategoryIds($oxid);
        /*echo '<pre>';
        print_r($aCategoryIds);
        echo '</pre>';*/
        if (count($aCategoryIds) != 0)
            $sCatIdList = "'" . implode("','", $aCategoryIds) . "'";
        else 
            $sCatIdList = '';
        
        // if childs allowed but child has no maincategory take the parent category
        /*if ($categoryId == '' && isset($this->tempParent['OXID']))
        {
            $categoryId = $this->getMainCategoryId($this->tempParent['OXID']);
        }*/

        if($sCatIdList != '')
        {
            $query="SELECT OXSEOURL
                    FROM oxseo
                    WHERE OXOBJECTID = '".$oxid."' AND
                    OXPARAMS IN(".$sCatIdList.") AND
                    OXLANG = ".$this->_config['langid'].
                    " LIMIT 1";

            $rs = mysql_query($query);
            if ($rs)
            {
                while($row = mysql_fetch_array($rs))
                {
                    // return seo url
                    $sLink = $this->sShopURL.'/'.$row['OXSEOURL'];
                    return $sLink;
                }
            }
        }
        
        // return normal url
        $sLink = $this->sShopURL.$this->_config['productLinkPrefix'].$oxid;
        return $sLink;
    }
    
    /**
     * get Price
     * 
     * @return string
     */
    public function getProductPrice()
    {
        if($this->_config['netPrices'])
        {
            // general vat
            $vat = $this->_config['generalVat'];
            
            // if product (parent /child) has own vat
            if(isset($this->tempProduct['OXVAT']) && !empty($this->tempProduct['OXVAT']))
            {
                $vat = $this->tempProduct['OXVAT'];
            }
            // if product parent has own vat
            if(isset($this->tempParent['OXVAT']) && !empty($this->tempParent['OXVAT']))
            {
                $vat = $this->tempParent['OXVAT'];
            }
            
            $price = $this->tempProduct['OXPRICE'];
            $price += $price * $vat / 100;
            
            return round($price, 2);
        }
        else
        {
            return $this->tempProduct['OXPRICE'];
        }
    }
    
    /**
     * get Unit Quantity
     * 
     * @return string
     */
    public function getProductUnitQuantity()
    {
        if ($this->tempProduct['OXPARENTID'] == '') {
            return $this->tempProduct['OXUNITQUANTITY'];
        }
        elseif ($this->tempProduct['OXUNITQUANTITY'] != 0) {
            return $this->tempProduct['OXUNITQUANTITY'];
        }
        else {
            return $this->tempParent['OXUNITQUANTITY'];
        }
    }
    
    /**
     * get Unit Quantity
     * 
     * @return string
     */
    public function getProductUnitName()
    {
        if ($this->tempProduct['OXPARENTID'] == '') {
            $unitName = $this->tempProduct['OXUNITNAME'];
        }
        elseif ($this->tempProduct['OXUNITNAME'] != '') {
            $unitName = $this->tempProduct['OXUNITNAME'];
        }
        else {
            $unitName = $this->tempParent['OXUNITNAME'];
        }
        
        //echo ' **'.$unitName.'** ';
        if (array_key_exists($unitName, $this->unitTranslation))
            return $this->unitTranslation[$unitName];
        else
            return $this->getProductCustomUnitName();
    }
    
    /**
     * get Unit Quantity
     * 
     * @return string
     */
    public function getProductCustomUnitName()
    {
        if ($this->tempProduct['OXPARENTID'] == '') {
            $unitName = $this->tempProduct['OXUNITNAME'];
            $unitQuantity = $this->tempProduct['OXUNITQUANTITY'];
        }
        elseif ($this->tempProduct['OXUNITNAME'] != '') {
            $unitName = $this->tempProduct['OXUNITNAME'];
            $unitQuantity = $this->tempProduct['OXUNITQUANTITY'];
        }
        else {
            $unitName = $this->tempParent['OXUNITNAME'];
            $unitQuantity = $this->tempParent['OXUNITQUANTITY'];
        }
        
        //$returnValue = preg_match('/[0-9]*/', $unitName, $matches, PREG_OFFSET_CAPTURE);
        /*if ((float)$matches[0][0] == 0)
            $baseamount = 1;
        else
            $baseamount = $matches[0][0];

        $amount = $unitQuantity * $baseamount;
        if (empty($amount))
            $amount = 1;
*/
        //$returnValue = preg_match('/[^0-9 ].*/', $unitName, $matches, PREG_OFFSET_CAPTURE);
        /*$customUnitName = $matches[0][0];
        echo ' ++'.$customUnitName.'++ ';*/
        
        //return $customUnitName;
        return $unitName;
    }
    
    /**
     * get Base Price as Number
     * 
     * @return string
     */
    public function getProductBasePrice()
    {
        if ($this->getProductUnitQuantity() != 0) {
            if ($this->tempProduct['OXPARENTID'] == '')             // not a variant
                return round($this->getProductPrice()/$this->getProductUnitQuantity(),2);
            elseif ($this->tempProduct['OXUNITQUANTITY'] == 0)      // variant without own unit price
                return round($this->getProductPrice()/$this->getProductUnitQuantity(),2);
            else                                                    // variant with an own unit price
                return round($this->getProductPrice()/$this->getProductUnitQuantity(),2);
        }
        else
            return 0;
    }
    
    /**
     * get Base Price as Text
     * 
     * @return string
     */
    public function getProductBasePriceText()
    {
        if ($this->getProductBasePrice() != 0) {
            return $this->getProductBasePrice() . ' €/' . $this->getProductUnitName();
        }
        else
            return '';
    }
    
    /**
     * get UVP
     * 
     * @return string
     */
    public function getUVP()
    {
        $uvp = '';
        if($this->tempProduct['OXTPRICE'] != '0')
        {
            $uvp = $this->tempProduct['OXTPRICE'];
        }
        print_r($this->tempProduct['OXTPRICE']);
        return $uvp;
    }
    
    /**
     * get UVP
     * 
     * @return string
     */
    public function setUnitTranslations()
    {
        $this->unitTranslation['_UNIT_KG'] = 'kg';
        $this->unitTranslation['_UNIT_G']  = 'g';
        $this->unitTranslation['_UNIT_L']  = 'l';
        $this->unitTranslation['_UNIT_ML'] = 'ml';
        $this->unitTranslation['_UNIT_M']  = 'm';
        $this->unitTranslation['_UNIT_CM'] = 'cm';
        $this->unitTranslation['_UNIT_MM'] = 'mm';
        $this->unitTranslation['_UNIT_M2'] = 'm²';
        $this->unitTranslation['_UNIT_M3'] = 'm³';
        
        return;
    }
    
    /**
     * exchange [{ oxcontent ident="..." }] in long description
     * 
     * @return string
     */
    public function exchOxContent($description)
    {
        preg_match('/\[{.*oxcontent.*="(.*)".*}]/', $description, $matches);
        if ( !empty($matches) ) {
            $description = preg_replace('/\[{.*oxcontent.*="(.*)".*}]/', $this->getContentPageText($matches[1]), $description);
        }

        return $description;
    }
}