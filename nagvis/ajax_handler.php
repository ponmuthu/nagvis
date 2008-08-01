<?PHP
/*****************************************************************************
 *
 * ajax_handler.php - Ajax handler for the NagVis frontend
 *
 * Copyright (c) 2004-2008 NagVis Project (Contact: lars@vertical-visions.de)
 *
 * License:
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2 as
 * published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
 *
 *****************************************************************************/

// Start the user session (This is needed by some caching mechanism)
@session_start();

// Set PHP error handling to standard level
error_reporting(E_ALL ^ E_STRICT);

// Include defines
require("./includes/defines/global.php");
require("./includes/defines/matches.php");

// Include functions
require("./includes/functions/debug.php");
require("./includes/functions/oldPhpVersionFixes.php");
require("./includes/functions/getuser.php");
require("./includes/functions/ajaxErrorHandler.php");

// Include needed global classes
require("./includes/classes/GlobalCore.php");
require("./includes/classes/GlobalMainCfg.php");
require("./includes/classes/GlobalMapCfg.php");
require("./includes/classes/GlobalLanguage.php");
require("./includes/classes/GlobalPage.php");
require("./includes/classes/GlobalMap.php");
require("./includes/classes/GlobalBackground.php");
require("./includes/classes/GlobalGraphic.php");
require("./includes/classes/GlobalBackendMgmt.php");

// Include needed nagvis classes
require("./includes/classes/NagVisMapCfg.php");
require("./includes/classes/NagVisMap.php");
require("./includes/classes/NagVisFrontend.php");
require("./includes/classes/NagVisAutoMap.php");

// Include needed nagvis object classes
require("./includes/classes/objects/NagVisObject.php");
require("./includes/classes/objects/NagVisStatefulObject.php");
require("./includes/classes/objects/NagVisStatelessObject.php");
require("./includes/classes/objects/NagiosHost.php");
require("./includes/classes/objects/NagVisHost.php");
require("./includes/classes/objects/NagiosService.php");
require("./includes/classes/objects/NagVisService.php");
require("./includes/classes/objects/NagiosHostgroup.php");
require("./includes/classes/objects/NagVisHostgroup.php");
require("./includes/classes/objects/NagiosServicegroup.php");
require("./includes/classes/objects/NagVisServicegroup.php");
require("./includes/classes/objects/NagVisMapObj.php");
require("./includes/classes/objects/NagVisShape.php");
require("./includes/classes/objects/NagVisTextbox.php");

// Load the core
$CORE = new GlobalCore();

// FIXME: This is a hack; TODO: create a class "AjaxFrontend" which
// handles this user authentication related things and the handling below
$CORE->MAINCFG->setRuntimeValue('user', getUser());

// Initialize var
if(!isset($_GET['action'])) {
	$_GET['action'] = '';
}

switch($_GET['action']) {
	case 'getObjectHoverMenu':
		if(!isset($_GET['objType']) || $_GET['objType'] == '') {
			// FIXME: Error handling
			echo 'Error: '.$CORE->LANG->getText('parameterObjTypeNotSet');
		} elseif(!isset($_GET['objName1']) || $_GET['objName1'] == '') {
			// FIXME: Error handling
			echo 'Error: '.$CORE->LANG->getText('parameterObjName1NotSet');
		} elseif($_GET['objType'] == 'service' && (!isset($_GET['objName2']) || $_GET['objName2'] == '')) {
			// FIXME: Error handling
			echo 'Error: '.$CORE->LANG->getText('parameterObjName2NotSet');
		} else  {
			// Initialize backend(s)
			$BACKEND = new GlobalBackendMgmt($CORE);
			
			$objConf = Array();
			
			/**
			 * There are two ways to get the configuration for an object. When the map
			 * parameter is set the configuration of the object on the map is read.
			 * When the map parameter is not set or empty the configurations from main
			 * configuration file is used.
			 */
			if(isset($_GET['map']) && $_GET['map'] != '') {
				// Initialize map configuration
				$MAPCFG = new NagVisMapCfg($CORE, $_GET['map']);
				// Read the map configuration file
				$MAPCFG->readMapConfig();
				
				if($_GET['map'] == '__automap') {
					$objConf['type'] = $_GET['objType'];
					$objConf['host_name'] = $_GET['objName1'];
				} else {
					if(is_array($objs = $MAPCFG->getDefinitions($_GET['objType']))){
						$count = count($objs);
						for($i = 0; $i < $count && count($objConf) >= 0; $i++) {
							if($_GET['objType'] == 'service') {
								if($objs[$i]['host_name'] == $_GET['objName1'] && $objs[$i]['service_description'] == $_GET['objName2']) {
									$objConf = $objs[$i];
									$objConf['id'] = $i;
								}
							} else {
								if($objs[$i][$_GET['objType'].'_name'] == $_GET['objName1']) {
									$objConf = $objs[$i];
									$objConf['id'] = $i;
								}
							}
						}
					} else {
						// FIXME: ERROR handling
						echo 'Error: '.$CORE->LANG->getText('foundNoObjectOfThatTypeOnMap');
					}
					
					if(count($objConf) > 0) {
						// merge with "global" settings
						foreach($MAPCFG->validConfig[$_GET['objType']] AS $key => &$values) {
							if((!isset($objConf[$key]) || $objConf[$key] == '') && isset($values['default'])) {
								$objConf[$key] = $values['default'];
							}
						}
					} else {
						// FIXME: Errorhandling
						// object not on map
						echo 'Error: '.$CORE->LANG->getText('ObjectNotFoundOnMap');
					}
				}
			} else {
				if($_GET['objType'] == 'service') {
					$objConf['host_name'] = $_GET['objName1'];
					$objConf['service_description'] = $_GET['objName2'];
				} else {
					$objConf[$_GET['objType'].'_name'] = $_GET['objName1'];
				}
				
				// Get settings from main configuration file by generating a temporary map
				$TMPMAPCFG = new NagVisMapCfg($CORE);
				
				// merge with "global" settings
				foreach($TMPMAPCFG->validConfig['global'] AS $key => &$values) {
					if((!isset($objConf[$key]) || $objConf[$key] == '') && isset($values['default'])) {
						$objConf[$key] = $values['default'];
					}
				}
			}
				
			switch($_GET['objType']) {
				case 'host':
					$OBJ = new NagVisHost($CORE, $BACKEND, $objConf['backend_id'], $objConf['host_name']);
				break;
				case 'service':
					$OBJ = new NagVisService($CORE, $BACKEND, $objConf['backend_id'], $objConf['host_name'], $objConf['service_description']);
				break;
				case 'hostgroup':
					$OBJ = new NagVisHostgroup($CORE, $BACKEND, $objConf['backend_id'], $objConf['hostgroup_name']);
				break;
				case 'servicegroup':
					$OBJ = new NagVisServicegroup($CORE, $BACKEND, $objConf['backend_id'], $objConf['servicegroup_name']);
				break;
				case 'map':
					$MAPCFG = new NagVisMapCfg($CORE, $objConf['map_name']);
					if($MAPCFG->checkMapConfigExists(0)) {
						$MAPCFG->readMapConfig();
					}
					$OBJ = new NagVisMapObj($CORE, $BACKEND, $MAPCFG);
					
					if(!$MAPCFG->checkMapConfigExists(0)) {
						$OBJ->summary_state = 'ERROR';
						$OBJ->summary_output = $CORE->LANG->getText('mapCfgNotExists', 'MAP~'.$objConf['map_name']);
					}
				break;
				case 'shape':
					$OBJ = new NagVisShape($CORE, $objConf['icon']);
				break;
				case 'textbox':
					$OBJ = new NagVisTextbox($CORE);
				break;
				default:
					$FRONTEND = new GlobalPage($CORE);
					$FRONTEND->messageToUser('ERROR', $CORE->LANG->getText('unknownObject', 'TYPE~'.$type.';MAPNAME~'.$this->getName()));
				break;
			}
			
			// Apply default configuration to object
			$OBJ->setConfiguration($objConf);
			
			$OBJ->fetchMembers();
			
			$OBJ->fetchState();
			
			$OBJ->fetchIcon();
			
			if(isset($OBJ->hover_url) && $OBJ->hover_url != '') {
				$code = $OBJ->readHoverUrl();
			} else {
				$code = $OBJ->readHoverTemplate();
			}
			
			echo "{ code: ".$code." }";
		}
	break;
	case 'getMapState':
		if(!isset($_GET['map']) || $_GET['map'] == '') {
			// FIXME: Error handling
			echo 'Error: '.$CORE->LANG->getText('parameterMapNotSet');
		} elseif($_GET['map'] == '__automap') {
			// FIXME: Error handling
			echo 'Error: '.$CORE->LANG->getText('automapNotSupportedHere');
		} else {
			// Initialize map configuration
			$MAPCFG = new NagVisMapCfg($CORE, $_GET['map']);
			$MAPCFG->readMapConfig();
			
			// Initialize backends
			$BACKEND = new GlobalBackendMgmt($CORE);
			
			$MAP = new NagVisMap($CORE, $MAPCFG, $BACKEND);
			
			$retArr = Array('summarState' => $MAP->MAPOBJ->getSummaryState(),'summaryOutput' => $MAP->MAPOBJ->getSummaryOutput());
			echo json_encode($retArr);
		}
	break;
	default:
		echo 'Error: '.$CORE->LANG->getText('unknownQuery');
	break;
}
?>
