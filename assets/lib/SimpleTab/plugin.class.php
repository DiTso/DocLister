<?php
namespace SimpleTab;
include_once (MODX_BASE_PATH . 'assets/snippets/DocLister/lib/DLTemplate.class.php');
include_once (MODX_BASE_PATH . 'assets/lib/APIHelpers.class.php');
require_once (MODX_BASE_PATH . 'assets/lib/Helpers/FS.php');

abstract class Plugin {
	public $modx = null;
	public $pluginName = '';
	public $params = array();
    public $table = '';
	public $tpl = '';
	public $jsListDefault = '';
	public $jsListCustom = '';
	public $cssListDefault = '';
	public $cssListCustom = '';
	public $pluginEvents = array();
    public $_table = '';
	protected $fs = null;
	
	public $DLTemplate = null;
	public $lang_attribute = '';

    /**
     * @param $modx
     * @param string $lang_attribute
     * @param bool $debug
     */
    public function __construct($modx, $lang_attribute = 'en', $debug = false) {
        $this->modx = $modx;
        $this->_table = $modx->getFullTableName($this->table);
        $this->lang_attribute = $lang_attribute;
        $this->params = $modx->event->params;
        if (!isset($this->params['template']) && $modx->event->name != 'OnEmptyTrash') {
            $this->params['template'] = array_pop($modx->getDocument($this->params['id'],'template','all','all'));
        }
        $this->DLTemplate = \DLTemplate::getInstance($this->modx);
        $this->fs = \Helpers\FS::getInstance();
    }

	public function clearFolders($ids = array(), $folder) {
        foreach ($ids as $id) $this->fs->rmDir($folder.$id.'/');
    }

    /**
     * @return string
     */
    public function prerender() {
        if (!$this->checkTable()) {
            $result = $this->createTable();
            if (!$result) {
                $this->modx->logEvent(0, 3, "Cannot create {$this->table} table.", $this->pluginName);
                return;
            }
			$this->registerEvents($this->pluginEvents);
        }
        $output = '';
    	$templates = isset($this->params['templates']) ? explode(',',$this->params['templates']) : false;
		$roles = isset($this->params['roles']) ? explode(',',$this->params['roles']) : false;
		if (!$templates || ($templates && !in_array($this->params['template'],$templates)) || ($roles && !in_array($_SESSION['mgrRole'],$roles))) return false;
		$plugins = $this->modx->pluginEvent;
		if(array_search('ManagerManager',$plugins['OnDocFormRender']) === false && !isset($this->modx->loadedjscripts['jQuery'])) {
			$output .= '<script type="text/javascript" src="'.$this->modx->config['site_url'].'assets/js/jquery/jquery-1.9.1.min.js"></script>';
            $this->modx->loadedjscripts['jQuery'] = array('version'=>'1.9.1');
            $output .='<script type="text/javascript">var jQuery = jQuery.noConflict(true);</script>';
		}
		$tpl = MODX_BASE_PATH.$this->tpl;
		if($this->fs->checkFile($tpl)) {
			$output .= '[+js+][+styles+]'.file_get_contents($tpl);
		} else {
			$this->modx->logEvent(0, 3, "Cannot load {$this->tpl} .", $this->pluginName);
		}
		return $output;
    }

    /**
     * @param $list
     * @param array $ph
     * @return string
     */
    public function renderJS($list,$ph = array()) {
    	$js = '';
    	$scripts = MODX_BASE_PATH.$list;
		if($this->fs->checkFile($scripts)) {
			$scripts = @file_get_contents($scripts);
			$scripts = $this->DLTemplate->parseChunk('@CODE:'.$scripts,$ph);
			$scripts = json_decode($scripts,true);
			$scripts = isset($scripts['scripts']) ? $scripts['scripts'] : $scripts['styles'];
			foreach ($scripts as $name => $params) {
				if (!isset($this->modx->loadedjscripts[$name]) && $this->fs->checkFile($params['src'])) {
					$this->modx->loadedjscripts[$name] = array('version'=>$params['version']);
					if (end(explode('.',$params['src'])) == 'js') {
						$js .= '<script type="text/javascript" src="' . $this->modx->config['site_url'] . $params['src'] . '"></script>';
					} else {
						$js .= '<link rel="stylesheet" type="text/css" href="'. $this->modx->config['site_url'] . $params['src'] .'">';
					}
				} else {
                    $this->modx->logEvent(0, 3, 'Cannot load '.$params['src'], $this->pluginName);
                }
			}
		} else {
			if ($list == $this->jsListDefault) {
				$this->modx->logEvent(0, 3, "Cannot load {$this->jsListDefault} .", $this->pluginName);
			} elseif ($list == $this->cssListDefault) {
				$this->modx->logEvent(0, 3, "Cannot load {$this->cssListDefault} .", $this->pluginName);
			}
		}
		return $js;
    }

	/**
	 * @return array
	 */
	public function getTplPlaceholders() {
		$ph = array ();
		return $ph;
	}

    /**
     * @return string
     */
    public function render() {
		$output = $this->prerender();
		if ($output !== false) {
			$ph = $this->getTplPlaceholders();
			$ph['js'] = $this->renderJS($this->jsListDefault,$ph) . $this->renderJS($this->jsListCustom,$ph);
			$ph['styles'] = $this->renderJS($this->cssListDefault,$ph) . $this->renderJS($this->cssListCustom,$ph);
			$output = $this->DLTemplate->parseChunk('@CODE:'.$output,$ph);
		}
		return $output;
    }

    /**
     * @return bool
     */
    public function checkTable() {
        $sql = "SHOW TABLES LIKE '{$this->_table}'";
        return $this->modx->db->getRecordCount( $this->modx->db->query($sql));
    }

    public function createTable() {
    	$sql = '';
    	return $this->modx->db->query($sql);
    }

	public function registerEvents($events = array(), $eventsType = '6') {
		$eventsTable = $this->modx->getFullTableName('system_eventnames');
		foreach ($events as $event) {
			$result = $this->modx->db->select('`id`',$eventsTable,"`name` = '{$event}'");
			if (!$this->modx->db->getRecordCount($result)) {
				$sql = "INSERT INTO {$eventsTable} VALUES (NULL, '{$event}', '{$eventsType}', '{$this->pluginName} Events')";
				if (!$this->modx->db->query($sql)) $this->modx->logEvent(0, 3, "Cannot register {$event} event.", $this->pluginName);
			}
		}
	}
}
