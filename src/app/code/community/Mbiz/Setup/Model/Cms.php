<?php
/**
 * This file is part of Mbiz_Setup for Magento.
 *
 * @license All rights reserved
 * @author Jacques Bodin-Hullin <j.bodinhullin@monsieurbiz.com> <jacquesbh>
 * @category Mbiz
 * @package Mbiz_Setup
 * @copyright Copyright (c) 2015 Monsieur Biz (http://monsieurbiz.com)
 */

/**
 * Cms Model
 * <p>This model is used to create blocks and pages.</p>
 * @package Mbiz_Setup
 */
class Mbiz_Setup_Model_Cms extends Mage_Core_Model_Abstract
{

    /**
     * Create a block
     * @param array $data The block's data
     *  <p>
     *  [
     *      'title'      => '', // Title of the block
     *      'identifier' => '', // Identifier of the block
     *      'content'    => '', // Content of the block
     *      'is_active'  => 1, // Active flag of the blog [0|1]
     *      'stores'     => [
     *          Mage_Core_Model_App::ADMIN_STORE_ID,
     *          Mage_Core_Model_App::DISTRO_STORE_ID,
     *      ],
     *      'update'     => 1, // Update or not
     *  ]
     *  </p>
     * @param string $directory Base directory where are the "content_file" files
     * @return Mage_Cms_Model_Block|array[Mage_Cms_Model_Block]
     */
    public function createBlock(array $data, $directory = null)
    {
        // Content
        if (isset($data['content_file']) && strlen($data['content_file'])) {
            $data['content'] = $this->_getContent($directory, $data['content_file']);
        }

        // Stores
        if (!isset($data['stores'])) {
            $data['stores'] = array(Mage_Core_Model_App::ADMIN_STORE_ID);
        } else {
            $data['stores'] = $this->_getStores($data['stores']);
        }

        if (isset($data['update']) && $data['update']) {
            $returnBlocks = array();
            // Get the blocks with the same identifier
            /* @var $blocks Mage_Cms_Model_Resource_Block_Collection */
            $blocks = Mage::getResourceModel('cms/block_collection');
            $blocks->addFieldToFilter('identifier', $data['identifier']);

            /* @var $cn Varien_Db_Adapter_Pdo_Mysql */
            $cn = $blocks->getConnection('core_write');

            // get the stores of the block
            $blocksStores = $cn->select()
                ->from($blocks->getTable('cms/block_store'))
                ->where('block_id IN (?)', $blocks->getColumnValues('block_id'))
                ->query()
                ->fetchAll()
            ;
            foreach ($blocksStores as $store) {
                // Assign stores to the block
                $block = $blocks->getItemById($store['block_id']);
                $stores = $block->getStores();
                if (null === $stores) {
                    $stores = array();
                }
                $stores[] = $store['store_id'];
                $block->setStores($stores);
            }

            // Update the blocks
            foreach ($blocks as $block) {
                // for each store in the updated data, we update only if stores match
                $blockStores = $block->getStores();
                foreach ($data['stores'] as $storeId) {
                    if (in_array($storeId, $blockStores)) {
                        $returnBlocks[] = $block
                            ->addData($data)
                            ->save()
                        ;
                        break;
                    }
                }
            }
            return $returnBlocks;
        } else {
            return Mage::getModel('cms/block')
                ->setData($data)
                ->save()
            ;
        }
    }

    /**
     * Add blocks
     * @param array $blocks The blocks
     *  <p>
     *  [
     *      [ // One data array per block
     *          'title'        => '', // Title of the block
     *          'identifier'   => '', // Identifier of the block
     *          'content'      => '', // Content of the block
     *          'content_file' => '', // File with the block's content
     *          'is_active'    => 1, // Active flag of the blog [0|1]
     *          'stores'       => [ The stores
     *              'admin',
     *              'default',
     *          ],
     *      ],
     *      // …
     *  ]
     *  </p>
     * @param string $directory Base directory where are the "content_file" files
     * @return array[Mage_Cms_Model_Block]
     */
    public function createBlocks(array $blocks, $directory = null)
    {
        $_blocks = array();
        foreach ($blocks as $block) {
            $_blocks[] = $this->createBlock($block, $directory);
        }
        return $_blocks;
    }

    /**
     * Add Page
     * @param array $data The page's data
     *  <p>This method can't update pages like createBlock update blocks.</p>
     *  <p>
     *  [
     *      'title'            => '', // Title of the block
     *      'identifier'       => '', // Identifier of the block
     *      'root_template'    => '', // Root template of the block
     *      'meta_keywords'    => '', // Meta keywords of the page
     *      'meta_description' => '', // Meta description of the page
     *      'content_heading'  => '', // Content title of the block
     *      'content'          => '', // Content of the block
     *      'content_file'     => '', // File with the block's content
     *      'is_active'        => 1, // Active flag of the blog [0|1]
     *      'stores'           => [ // The stores
     *          'admin',
     *          'default',
     *      ],
     *      // …
     *  ]
     *  </p>
     * @param string $directory Base directory where are the "content_file" files
     * @return Mage_Cms_Model_Page
     */
    public function createPage(array $data, $directory = null)
    {
        // Content
        if (isset($data['content_file']) && strlen($data['content_file'])) {
            $data['content'] = $this->_getContent($directory, $data['content_file']);
        }

        // Stores
        if (!isset($data['stores'])) {
            $data['stores'] = array(Mage_Core_Model_App::ADMIN_STORE_ID);
        } else {
            $data['stores'] = $this->_getStores($data['stores']);
        }

        $page = Mage::getModel('cms/page')
            ->setStores($data['stores'])
            ->load($data['identifier'], 'identifier');

        return $page->addData($data)
            ->save();
    }

    /**
     * Add pages
     * @param array $pages The pages
     *  <p>
     *  [
     *      [ // One data array per page
     *          'title'            => '', // Title of the block
     *          'identifier'       => '', // Identifier of the block
     *          'root_template'    => '', // Root template of the block
     *          'meta_keywords'    => '', // Meta keywords of the page
     *          'meta_description' => '', // Meta description of the page
     *          'content_heading'  => '', // Content title of the block
     *          'content'          => '', // Content of the block
     *          'content_file'     => '', // File with the block's content
     *          'is_active'        => 1, // Active flag of the blog [0|1]
     *          'stores'           => [ // The stores
     *              'admin',
     *              'default',
     *          ],
     *          // …
     *      ],
     *      // …
     *  ]
     *  </p>
     * @param string $directory Base directory where are the "content_file" files
     * @return array
     */
    public function createPages(array $pages, $directory = null)
    {
        $_pages = array();
        foreach ($pages as $page) {
            $_pages[] = $this->createPage($page, $directory);
        }
        return $_pages;
    }

    /**
     * Create pages and blocks from a JSON file
     * @param string $jsonFilename JSON filename
     *  <p>
     *      JSON example: fullfill the pages and the blocks by their data
     *      {
     *          "blocks": [
     *              {
     *                  "title": "",
     *                  "identifier": "",
     *                  "content": "",
     *                  "content_file": null,
     *                  "is_active": 1,
     *                  "stores": ["admin","default"]
     *              }
     *          ],
     *          "pages": [
     *              {
     *                  "title": "",
     *                  "identifier": "",
     *                  "root_template": "",
     *                  "meta_keywords": "",
     *                  "meta_description": "",
     *                  "content_heading": "",
     *                  "content": "",
     *                  "content_file": null,
     *                  "is_active": 1,
     *                  "stores": ["admin","default"]
     *              }
     *          ]
     *      }
     *  </p>
     * @param string $directory Base directory where are the "content_file" files
     * @throws ErrorException If JSON file doesn't exist
     * @throws ErrorException If JSON file is malformed
     * @return array Blocks and pages
     */
    public function createCmsFromJsonFile($jsonFilename, $directory = null)
    {
        if (!is_file($jsonFilename)) {
            throw new ErrorException("JSON file doesn't exist.");
        }

        $json   = file_get_contents($jsonFilename);

        try {
            $data   = Mage::helper('core')->jsonDecode($json);
            $pages  = isset($data['pages'])
                ? $this->createPages($data['pages'], $directory)
                : array();
            $blocks = isset($data['blocks'])
                ? $this->createBlocks($data['blocks'], $directory)
                : array();
            return array(
                'pages'  => $pages,
                'blocks' => $blocks,
            );
        } catch (Zend_Json_Exception $e) {
            throw new ErrorException(sprintf(
                "The JSON is malformed (%s) in file %s.",
                $e->getMessage(),
                $jsonFilename
            ));
        }
    }

    /**
     * Retrieve the content of a file
     * @param string $directory The base directory
     * @param string $filename The filename
     * @throws ErrorException If file not found
     * @return string
     */
    protected function _getContent($directory, $filename)
    {
        $file = rtrim($directory, "/") . DS . ltrim($filename, "/");

        if (!is_file($file)) {
            throw new ErrorException(sprintf("File not found (%s).", $filename));
        }

        return file_get_contents($file);
    }

    /**
     * Retrieve the stores
     * @param array $stores
     * @return array[int]
     */
    protected function _getStores(array $stores)
    {
        $_stores = array();
        foreach ($stores as $storeIdentifier) {
            if (($store = Mage::app()->getStore($storeIdentifier)) && !is_null($store->getId())) {
                $_stores[] = $store->getId();
            }
        }
        return $_stores;
    }

}
