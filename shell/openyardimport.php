<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category    Mage
 * @package     Mage_Shell
 * @copyright   Copyright (c) 2009 Irubin Consulting Inc. DBA Varien (http://www.varien.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

require_once 'abstract.php';

/**
 * Magento Compiler Shell Script
 *
 * @category    Mage
 * @package     Mage_Shell
 * @author      Magento Core Team <core@magentocommerce.com>
 */
class Mage_Shell_Openyard extends Mage_Shell_Abstract
{
    /**
     * Get Indexer instance
     *
     * @return Mage_Index_Model_Indexer
     */
    protected function _getIndexer()
    {
        return $this->_factory->getSingleton($this->_factory->getIndexClassAlias());
    }

    /**
     * Parse string with indexers and return array of indexer instances
     *
     * @param string $string
     * @return array
     */
    protected function _parseIndexerString($string)
    {
        $processes = array();
        if ($string == 'all') {
            $collection = $this->_getIndexer()->getProcessesCollection();
            foreach ($collection as $process) {
                if ($process->getIndexer()->isVisible() === false) {
                    continue;
                }
                $processes[] = $process;
            }
        } else if (!empty($string)) {
            $codes = explode(',', $string);
            $codes = array_map('trim', $codes);
            $processes = $this->_getIndexer()->getProcessesCollectionByCodes($codes);
            foreach($processes as $key => $process) {
                if ($process->getIndexer()->getVisibility() === false) {
                    unset($processes[$key]);
                }
            }
            if ($this->_getIndexer()->hasErrors()) {
                echo implode(PHP_EOL, $this->_getIndexer()->getErrors()), PHP_EOL;
            }
        }
        return $processes;
    }

    /**
     * Run script
     *
     */
    public function run()
    {
        if ($this->getArg('hello')) {

            echo "Hello " . $this->getArg('hello') . "\n";
        }
        elseif( $this->getArg('reload-data')) {

            echo "Bringing the DB back..\n";
            $variable = exec('mysql -h d91d0b8164b35f211c3484a2b3194088f1b10454.rackspaceclouddb.com -u magento -pmagentogo magento_dev2 < /home/magento_fresh_ce1_8.sql');
            echo "It is now back.. Thank you for playing. \n";

        } elseif( $this->getArg('delete-product-batch') ){

            $products = Mage::getResourceModel('catalog/product_collection')
                ->addAttributeToFilter('batch_import_key', $this->getArg('delete-product-batch'))
                ->getAllIds();

            foreach ($products as $key => $productId)
            {
                try {
                    $product = Mage::getSingleton('catalog/product')->load($productId);
                    Mage::dispatchEvent('catalog_controller_product_delete', array('product' => $product));
                    $product->delete();
                } catch (Exception $e) {
                    echo "<br />Cannot delete product ID: $productId";
                }

                echo "Deleted ... " . $productId . "\n";
            }

            echo "Delete Complete.. Don't forget to flush cache and shit \n";


        }elseif($this->getArg('import')){

            $this->baseSetup();

            $file = fopen('../var/import/openyard_export_3.csv', 'r');
            $i = 0;
            $limit = $this->getArg('import');
            $headers = [];
            while (($line = fgetcsv($file)) !== FALSE) {
                if ( $i == 0 ) { $headers = $line; }
                $data = array_combine($headers,$line);

                if($i > 0){
                    $this->saveProduct($data);
                }
                if ($i++ == $limit) break;

            }
            fclose($file);


            //Put Products in Categories
            $this->putProductsInCategories();


            echo "done importing \n";

        } else {
            echo $this->usageHelp();
        }
    }


    function existsAttributeSet($name){

        return ($this->getAttributeSetId($name) == NULL)? false : true;
    }

    function getAttributeSetId($name){
        $entityTypeId = Mage::getModel('eav/entity')
            ->setType('catalog_product')
            ->getTypeId();
        $attributeSetName   = $name;
        $attributeSetId     = Mage::getModel('eav/entity_attribute_set')
            ->getCollection()
            ->setEntityTypeFilter($entityTypeId)
            ->addFieldToFilter('attribute_set_name', $attributeSetName)
            ->getFirstItem()
            ->getAttributeSetId();

        return $attributeSetId;
    }

    function getTag($tag){
        $oTag = Mage::getModel('tag/tag')
            ->load($tag, 'name');

        if( $oTag->isObjectNew() ){
            $oTag->setName($tag)->setStatus(1)->save();
        }
        return $oTag;
    }

    function tagProduct($productid, $tag){

        Mage::getModel('tag/tag_relation')
            ->setTagId( $this->getTag($tag)->getId() )
            ->setCustomerId(Mage::getSingleton('customer/session')->getCustomerId())
            ->setStoreId( Mage::app()->getStore()->getId() )
            ->setActive(1)
            ->setProductId($productid)
            ->save();

        return true;
    }

    function baseSetup(){


        //Duplicate Magentos Default for Later
        if(!$this->existsAttributeSet('MageDefault')){
            $this->createAttributeSet('MageDefault', 'Default' );
        }

        //Move Unused
        $this->addAttributeToSet('country_of_manufacture','Default','Unused',100);

        //move Descriptions
        $this->addAttributeToSet('description','Default','Description', 2);
        $this->addAttributeToSet('short_description','Default','Description');

        //move manufacturer
        $this->addAttributeToSet('manufacturer','Default');

        // batch-import-key
        $this->createAttribute('batch_import_key',-1,[
            'frontend_input'                => 'text',
            'is_searchable'                 => '0',
            'is_visible_in_advanced_search' => '0',
            'is_comparable'                 => '0',
            'is_used_for_promo_rules'       => '0',
            'is_html_allowed_on_front'      => '0',
            'is_visible_on_front'           => '0',
            'used_in_product_listing'       => '0',
            'used_for_sort_by'              => '0',
            'is_configurable'               => '0',
            'is_filterable'                 => '0',
            'is_filterable_in_search'       => '0',
            'backend_type'                  => 'varchar',
            'default_value'                 => '',
        ]);

        $this->addAttributeToSet('batch_import_key');

        // add availability date
        $this->createAttribute('availability_date',-1,['frontend_input' => 'date']);
        $this->addAttributeToSet('availability_date');

        // add availability date
        $this->createAttribute('unit_of_measure');
        $this->addAttributeToSet('unit_of_measure');

        // add old product id
        $this->createAttribute('old_product_id',-1,['frontend_input' => 'text']);
        $this->addAttributeToSet('old_product_id','Default','Meta Information');

        // add old sku id
        $this->createAttribute('old_sku_id',-1,['frontend_input' => 'text']);
        $this->addAttributeToSet('old_sku_id','Default','Meta Information');

        // Create Categories
        $this->createCategories();

    }

    function createCategoryPath($aPath){

        $popped = $aPath;
        array_pop($popped);

        if(sizeof($popped) == 0){
            $cat = Mage::getResourceModel('catalog/category_collection')->addFieldToFilter('name', 'Default Category');
            $parent = $cat->getFirstItem();
        }else{
            $cat = Mage::getResourceModel('catalog/category_collection')->addFieldToFilter('url_key', $this->getCategoryUrl($popped));
            $parent = $cat->getFirstItem();
        }

        $check = Mage::getResourceModel('catalog/category_collection')->addFieldToFilter('url_key', $this->getCategoryUrl($aPath) );
        if( $check->getSize() > 0 ){
            return $check->getFirstItem()->getEntityId();
        }


        $category = new Mage_Catalog_Model_Category();

        $category->setName( ucwords(end($aPath)) );
        $category->setUrlKey( $this->getCategoryUrl($aPath));
        $category->setIsActive(1);
        $category->setDisplayMode('PRODUCTS');
        $category->setIsAnchor(1);

        $category->setPath( $parent->getPath() );

        $category->save();

        return $category->getId();


    }

    function getCategoryUrl($aPath){

        foreach( $aPath as $index=>$value){
            $newValue = strtr($value, array(
                '.'=>'',
                "'"=>'',
            ));
            $aPath[$index] = preg_replace('/[^\da-zA-Z ]/i', '', $newValue);
        }

        return str_replace(" ","-", implode('-', $aPath ) );
    }

    function getProductsByTags(array $tags){

        if(isset( $tags['oldsite'])) {
            echo "oldsite";
            exit;
        }


        $baseIds = Mage::getResourceModel('tag/product_collection')
            ->addAttributeToSelect('entity_id')
            ->addTagFilter( $this->getTag( array_pop($tags) )->getId() )
            ->getColumnValues('entity_id');

        $products = $baseIds;

        if(sizeof($tags) == 0) return $products;

        foreach($tags as $index=>$tag){

            $additionalIds = Mage::getResourceModel('tag/product_collection')
                ->addAttributeToSelect('entity_id')
                ->addTagFilter( $this->getTag( $tag )->getId() )
                ->getColumnValues('entity_id');

            $products = array_intersect($products,$additionalIds);

        }

        return $products;
    }

    function putProductsInCategories(){

        print_r('Putting Products In Categories' . "\n");

        $file = fopen('../var/import/old_catagories.csv', 'r');
        $i = 0;
        $limit = 200;
        $headers = [];
        $i = 0;

        $prodCats = array();

        while (($line = fgetcsv($file)) !== FALSE) {
            if ( $i == 0 ) { $headers = $line; $i++; continue;}
            $data = array_combine($headers,$line);

            $treeArray = explode(':',$data['treedata']);
            $categoryRow = explode(',', $treeArray[0] );

            $products = $this->getProductsByTags($categoryRow);


            $category = Mage::getResourceModel('catalog/category_collection')->addFieldToFilter('url_key', $this->getCategoryUrl($categoryRow) );
            $category = $category->getFirstItem();

            echo "found " . sizeof($products) . " for category (" . $category->getId() . ") " . $this->getCategoryUrl($categoryRow) . "\n";

            //print_r($category);

            //$category = Mage::getResourceModel('catalog/category_collection')->addFieldToFilter('url_key', $this->getCategoryUrl($categoryRow) );
            //$category = $category->getFirstItem();

            foreach($products as $index=>$prodId){
                $prodCats[$prodId][] = $category->getId();
            }




            if($i++ > ($limit - 1) ){ break;}


        }
        fclose($file);

        foreach($prodCats as $prodid=>$categoryArray){
            $product = Mage::getModel('catalog/product')->load($prodid);
            $product->setCategoryIds( $categoryArray );
            $product->save();
            echo "Saved " . sizeof($categoryArray) . " Categories to productid " . $prodid . "\n";
        }

        exit;
    }

    function createCategories(){

        print_r('creating categories' . "\n");

        $file = fopen('../var/import/old_catagories.csv', 'r');
        $i = 0;
        $limit = 200;
        $headers = [];
        $i = 0;
        while (($line = fgetcsv($file)) !== FALSE) {
            if ( $i == 0 ) { $headers = $line; $i++; continue;}
            $data = array_combine($headers,$line);

            $treeArray = explode(':',$data['treedata']);
            $categoryRow = explode(',', $treeArray[0] );

            $parentid = $this->createCategoryPath($categoryRow);
            echo "Created Path .. " . $parentid . " for! " . $this->getCategoryUrl($categoryRow) . "\n";

            if($i++ > ($limit - 1) ){ break;}
        }
        fclose($file);

    }

    function getAttributeOptionId($attributecode,$optionlabel){
        $option_arr = array();
        $attribute = Mage::getModel('eav/config')->getAttribute('catalog_product', $attributecode);
        foreach ($attribute->getSource()->getAllOptions(false) as $option) {
            $option_arr[$option['value']] = $option['label'];
        }

        $option_arr = array_flip($option_arr);

        if(isset($option_arr[$optionlabel])){
            return $option_arr[$optionlabel];
        }

        return false;

    }

    function forceAttributeId($attribute, $return = -1){
        if(!$this->getAttributeId($attribute)){
            $this->createAttribute($attribute);
        }

        if($return == 'code'){ return $attribute; }

        return $this->getAttributeId($attribute);
    }

    function forcedAttributeOptionId($attribute,$option){

        $this->forceAttributeId($attribute);

        if($labelid = $this->getAttributeOptionId($attribute,$option)){
            return $labelid;
        }


        $this->addOptionToAttribute($attribute,$option);

        return $this->getAttributeOptionId($attribute,$option);

    }


    function getAttributeId($attribute){

        $attribute = Mage::getModel('catalog/product')->getResource()->getAttribute($attribute);

        if(!$attribute){
            return false;
        }

        return $attribute->getId();
    }

    function removeProductAttribute($attribute){
        $setup = new Mage_Eav_Model_Entity_Setup('core_setup');
        $entityTypeCode = 'catalog_product';
        $attributeCode    = $attribute;

        $setup->removeAttribute($entityTypeCode , $attributeCode);
    }

    function saveProduct($data){

        if(!$this->existsAttributeSet($data['attribute_set'])){
            $this->createAttributeSet($data['attribute_set'], 'Default' );
        }

        $product = Mage::getModel('catalog/product');
        $product->setName($data['name']);
        $product->setAttributeSetId( $this->getAttributeSetId($data['attribute_set'])); // need to look this up
        $product->setSku($data['skucode']);

        $product->setData( $this->forceAttributeId('batch_import_key','code'), 3);

        $product->setDescription($data['desc']);
        $product->setShortDescription($data['desc']);

        $product->setData('manufacturer', $this->forcedAttributeOptionId('manufacturer', $data['brand_name']) );

        $product->setData( $this->forceAttributeId('unit_of_measure','code') , $this->forcedAttributeOptionId('unit_of_measure', $data['unit_of_measure']) );

        //Availability Date
        $product->setData('availability_date', $data['Avail']);

        //Meta
        $product->setMetaKeyword($data['taglist']);
        $product->setMetaDescription('Are you ready to buy ' . $data['name'] . ' Well, you have come to the right place!');
        $product->setData('old_sku_id', $data['id']);
        $product->setData('old_product_id', $data['prodid']);

        //Pricing
        $product->setPrice($data['price']);
        $product->setCost($data['cost']);
        $product->setMsrp($data['msrp']);

        $product->setTypeId('simple');
        $product->setCategoryIds('35'); // need to look these up
        $product->setWeight($data['wgt']);
        $product->setTaxClassId(0); // taxable goods
        $product->setVisibility($data['visibility']); // catalog, search
        $product->setStatus(1); // enabled

        // assign product to the default website
        $product->setWebsiteIds(array($data['website']));

        $product->save();

        // tag the product.
        if( $data['taglist'] != ''){
            $tagArray = explode(',',$data['taglist']);
            foreach($tagArray as $i=>$tag ){
                $this->tagProduct($product->getId(),$tag);
            }
        }


        echo "Saved ... " . $data['skucode'] . "\n";

    }


    function addOptionToAttribute($attribute,$value){
        $attribute_code=Mage::getModel('eav/entity_attribute')->getIdByCode('catalog_product', $attribute);
        $attributeInfo = Mage::getModel('eav/entity_attribute')->load($attribute_code);
        $attribute_table = Mage::getModel('eav/entity_attribute_source_table')->setAttribute($attributeInfo);
        $options = $attribute_table->getAllOptions(false);
        //$options = $attributeInfo->getSource()->getAllOptions(false);
        $_optionArr = array('value'=>array(), 'order'=>array(), 'delete'=>array());
        foreach ($options as $option){
            $_optionArr['value'][$option['value']] = array($option['label']);
            $checkarray[] = $option['label'];
        }
        if (!in_array($value, $checkarray)) {
            $_optionArr['value']['option_1'] = array($value);
            $attributeInfo->setOption($_optionArr);
            $attributeInfo->save();
            return true;
        }
        return false;
    }

    //Delete any option from manufacturer like 'adidas'
    function removeOptionFromAttribute($attribute,$label){
        $attribute_code=Mage::getModel('eav/entity_attribute')->getIdByCode('catalog_product', $attribute);
        $attributeInfo = Mage::getModel('eav/entity_attribute')->load($attribute_code);
        $attribute_table = Mage::getModel('eav/entity_attribute_source_table')->setAttribute($attributeInfo);
        $options = $attribute_table->getAllOptions(false);
//$options = $attributeInfo->getSource()->getAllOptions(false);
        $_optionArr = array('value'=>array(), 'order'=>array(), 'delete'=>array());
        foreach ($options as $option){
            $_optionArr['value'][$option['value']] = array($option['label']);
            if($label == $option['label']){
                $_optionArr['delete'][$option['value']] = true;
            }
        }

        if(sizeof($_optionArr) > 0){
            $attributeInfo->setOption($_optionArr);
            $attributeInfo->save();
            return true;
        }
        return false;
    }



    /**
     * Create an atribute-set.
     *
     * For reference, see Mage_Adminhtml_Catalog_Product_SetController::saveAction().
     *
     * @return array|false
     */
    function createAttributeSet($setName, $copyGroupsFromID = -1)
    {

        if( !is_numeric($copyGroupsFromID)){
            $model=Mage::getModel('eav/entity_setup','core_setup');
            $copyGroupsFromID=$model->getAttributeSetId('catalog_product','Default');
        }


        $setName = trim($setName);

        Mage::log("Creating attribute-set with name [$setName].");

        if($setName == '')
        {
            Mage::log("Could not create attribute set with an empty name.");
            return false;
        }

        //>>>> Create an incomplete version of the desired set.

        $model = Mage::getModel('eav/entity_attribute_set');

        // Set the entity type.

        $entityTypeID = Mage::getModel('catalog/product')->getResource()->getTypeId();
        Mage::log("Using entity-type-ID ($entityTypeID).");

        $model->setEntityTypeId($entityTypeID);

        // We don't currently support groups, or more than one level. See
        // Mage_Adminhtml_Catalog_Product_SetController::saveAction().

        Mage::log("Creating vanilla attribute-set with name [$setName].");

        $model->setAttributeSetName($setName);

        // We suspect that this isn't really necessary since we're just
        // initializing new sets with a name and nothing else, but we do
        // this for the purpose of completeness, and of prevention if we
        // should expand in the future.
        $model->validate();

        // Create the record.

        try
        {
            $model->save();
        }
        catch(Exception $ex)
        {
            Mage::log("Initial attribute-set with name [$setName] could not be saved: " . $ex->getMessage());
            return false;
        }

        if(($id = $model->getId()) == false)
        {
            Mage::log("Could not get ID from new vanilla attribute-set with name [$setName].");
            return false;
        }

        Mage::log("Set ($id) created.");

        //<<<<

        //>>>> Load the new set with groups (mandatory).

        // Attach the same groups from the given set-ID to the new set.
        if($copyGroupsFromID !== -1)
        {
            Mage::log("Cloning group configuration from existing set with ID ($copyGroupsFromID).");

            $model->initFromSkeleton($copyGroupsFromID);
        }

        // Just add a default group.
        else
        {
            Mage::log("Creating default group [{$this->groupName}] for set.");

            $modelGroup = Mage::getModel('eav/entity_attribute_group');
            $modelGroup->setAttributeGroupName($this->groupName);
            $modelGroup->setAttributeSetId($id);

            // This is optional, and just a sorting index in the case of
            // multiple groups.
            // $modelGroup->setSortOrder(1);

            $model->setGroups(array($modelGroup));
        }

        //<<<<

        // Save the final version of our set.

        try
        {
            $model->save();
        }
        catch(Exception $ex)
        {
            Mage::log("Final attribute-set with name [$setName] could not be saved: " . $ex->getMessage());
            return false;
        }

        //print_r(get_class($modelGroup));
        //exit;

        //if(($groupID = $modelGroup->getId()) == false)
        //{
        //    Mage::log("Could not get ID from new group [$groupName].");
        //    return false;
        //}

        //Mage::log("Created attribute-set with ID ($id) and default-group with ID ($groupID).");

        //return array(
        //    'SetID'     => $id,
        //    'GroupID'   => $groupID,
        //);

        return array('newId' => $id);
    }


    /**
     * Create an attribute.
     *
     * For reference, see Mage_Adminhtml_Catalog_Product_AttributeController::saveAction().
     *
     * @return int|false
     */
    function createAttribute($attributeCode, $labelText = -1, $overrides = -1, $values = -1, $productTypes = -1, $setInfo = -1)
    {

        if($labelText === -1){
            $labelText = ucwords(str_replace("_"," ",$attributeCode));
        }

        $labelText = trim($labelText);
        $attributeCode = trim($attributeCode);

        if($labelText == '' || $attributeCode == '')
        {
            Mage::log("Can't import the attribute with an empty label or code.  LABEL= [$labelText]  CODE= [$attributeCode]");
            return false;
        }

        if($values === -1)
            $values = array();

        if($productTypes === -1)
            $productTypes = array();

        if($setInfo !== -1 && (isset($setInfo['SetID']) == false || isset($setInfo['GroupID']) == false))
        {
            Mage::log("Please provide both the set-ID and the group-ID of the attribute-set if you'd like to subscribe to one.");
            return false;
        }

        Mage::log("Creating attribute [$labelText] with code [$attributeCode].");

        //>>>> Build the data structure that will define the attribute. See
        //     Mage_Adminhtml_Catalog_Product_AttributeController::saveAction().

        $data = array(
            'is_global'                     => '0',
            'frontend_input'                => 'select',
            'default_value_text'            => '',
            'default_value_yesno'           => '0',
            'default_value_date'            => '',
            'default_value_textarea'        => '',
            'is_unique'                     => '0',
            'is_required'                   => '0',
            'frontend_class'                => '',
            'is_searchable'                 => '1',
            'is_visible_in_advanced_search' => '1',
            'is_comparable'                 => '1',
            'is_used_for_promo_rules'       => '1',
            'is_html_allowed_on_front'      => '0',
            'is_visible_on_front'           => '1',
            'used_in_product_listing'       => '1',
            'used_for_sort_by'              => '0',
            'is_configurable'               => '0',
            'is_filterable'                 => '1',
            'is_filterable_in_search'       => '1',
            'backend_type'                  => 'varchar',
            'default_value'                 => '',
        );

        if(is_array($overrides)){
            $data = array_merge($data,$overrides);
        }

        // Now, overlay the incoming values on to the defaults.
        foreach($values as $key => $newValue)
            if(isset($data[$key]) == false)
            {
                $this->logError("Attribute feature [$key] is not valid.");
                return false;
            }

            else
                $data[$key] = $newValue;

        // Valid product types: simple, grouped, configurable, virtual, bundle, downloadable, giftcard
        $data['apply_to']       = $productTypes;
        $data['attribute_code'] = $attributeCode;
        $data['frontend_label'] = array(
            0 => $labelText,
            1 => '',
            3 => '',
            2 => '',
            4 => '',
        );

        //<<<<

        //>>>> Build the model.

        $model = Mage::getModel('catalog/resource_eav_attribute');

        $model->addData($data);

        if($setInfo !== -1)
        {
            $model->setAttributeSetId($setInfo['SetID']);
            $model->setAttributeGroupId($setInfo['GroupID']);
        }

        $entityTypeID = Mage::getModel('eav/entity')->setType('catalog_product')->getTypeId();
        $model->setEntityTypeId($entityTypeID);

        $model->setIsUserDefined(1);

        //<<<<

        // Save.

        try
        {
            $model->save();
        }
        catch(Exception $ex)
        {
            Mage::log("Attribute [$labelText] could not be saved: " . $ex->getMessage());
            return false;
        }

        $id = $model->getId();

        Mage::log("Attribute [$labelText] has been saved as ID ($id).");

        return $id;
    }




    function addAttributeToSet($attribute, $set = 'Default', $group = 'General', $group_sortorder = null, $attribute_sortorder = null){
        $model=Mage::getModel('eav/entity_setup','core_setup');
        $attributeid = $this->forceAttributeId($attribute);
        $attributeSetId=$model->getAttributeSetId('catalog_product', $set);

        $entityTypeId = Mage::getModel('eav/entity')
            ->setType('catalog_product')
            ->getTypeId();

        if(is_null($group_sortorder)){
            $model->addAttributeGroup($entityTypeId, $attributeSetId, $group);
        }else{
            echo "set group sort to " . $group_sortorder . "\n";
            $model->addAttributeGroup($entityTypeId, $attributeSetId, $group, $group_sortorder);
        }

        $attributeGroupId=$model->getAttributeGroup('catalog_product',$attributeSetId, $group);


        //add attribute to a set (GROUP)
        if(is_null($attribute_sortorder)){
            $model->addAttributeToGroup($entityTypeId,$attributeSetId,$attributeGroupId['attribute_group_id'],$attributeid);
        }else{
            $model->addAttributeToGroup($entityTypeId,$attributeSetId,$attributeGroupId['attribute_group_id'],$attributeid, $attribute_sortorder);
        }
        return true;
    }

    /**
     * Retrieve Usage Help Message
     *
     */
    public function usageHelp()
    {
        return <<<USAGE
Usage:  php openyardimport.php -- [options]

  --hello <name>                Says Hello to Name
  --import <qty>                Number of products to import
  --reload-data                 Reloads the Database back to a previous point
  --delete-product-batch <id>   Deletes all products with the specific batch_import_key
  help                          This help

USAGE;
    }
}

$shell = new Mage_Shell_Openyard();
$shell->run();
