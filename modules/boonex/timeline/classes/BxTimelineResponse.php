<?php defined('BX_DOL') or die('hack attempt');
/**
 * Copyright (c) UNA, Inc - https://una.io
 * MIT License - https://opensource.org/licenses/MIT
 *
 * @defgroup    Timeline Timeline
 * @ingroup     UnaModules
 *
 * @{
 */

class BxTimelineResponse extends BxBaseModNotificationsResponse
{
    public function __construct()
    {
        parent::__construct();

        $this->_oModule = BxDolModule::getInstance('bx_timeline');
    }

    /**
     * Overwtire the method of parent class.
     *
     * @param BxDolAlerts $oAlert an instance of alert.
     */
    public function response($oAlert)
    {
    	$iObjectPrivacyView = $this->_getObjectPrivacyView($oAlert->aExtras);
        if($iObjectPrivacyView == BX_DOL_PG_HIDDEN)
            return;

        $aHandler = $this->_oModule->_oConfig->getHandlers($oAlert->sUnit . '_' . $oAlert->sAction);
        switch($aHandler['type']) {
            case BX_BASE_MOD_NTFS_HANDLER_TYPE_INSERT:
                $iOwnerId = $oAlert->iSender;
                if($iObjectPrivacyView < 0) {
                    $iOwnerId = abs($iObjectPrivacyView);
                    $iObjectPrivacyView = $this->_oModule->_oConfig->getPrivacyViewDefault('object');
                }

                $sContent = !empty($oAlert->aExtras) && is_array($oAlert->aExtras) ? serialize(bx_process_input($oAlert->aExtras)) : '';

                $iId = $this->_oModule->_oDb->insertEvent(array(
                    'owner_id' => $iOwnerId,
                    'type' => $oAlert->sUnit,
                    'action' => $oAlert->sAction,
                    'object_id' => $oAlert->iObject,
                    'object_privacy_view' => $iObjectPrivacyView,
                    'content' => $sContent,
                    'title' => '',
                    'description' => ''
                ));

                if(!empty($iId))
                    $this->_oModule->onPost($iId);

                //TODO: Remove the call and the function itself if GROUPING feature won't be used.
                $this->_oModule->_oDb->updateSimilarObject($iId, $oAlert);
                break;

            case BX_BASE_MOD_NTFS_HANDLER_TYPE_UPDATE:
                $aParamsSet = array(
                	'content' => !empty($oAlert->aExtras) && is_array($oAlert->aExtras) ? serialize(bx_process_input($oAlert->aExtras)) : ''
                );

                if($iObjectPrivacyView < 0)
                    $aParamsSet = array_merge($aParamsSet, array(
                        'owner_id' => abs($iObjectPrivacyView),
                        'object_privacy_view' => $this->_oModule->_oConfig->getPrivacyViewDefault('object'), 
                    ));
                
                $this->_oModule->_oDb->updateEvent($aParamsSet, array('type' => $oAlert->sUnit, 'object_id' => $oAlert->iObject));
                break;

            case BX_BASE_MOD_NTFS_HANDLER_TYPE_DELETE:
            	if($oAlert->sUnit == 'profile' && $oAlert->sAction == 'delete') {
            		$aEvents = $this->_oModule->_oDb->getEvents(array('browse' => 'owner_id', 'value' => $oAlert->iObject));
            		foreach($aEvents as $aEvent)
            			$this->_oModule->deleteEvent($aEvent);

            		if(isset($oAlert->aExtras['delete_with_content']) && $oAlert->aExtras['delete_with_content']) {
						$aEvents = $this->_oModule->_oDb->getEvents(array('browse' => 'common_by_object', 'value' => $oAlert->iObject));
						foreach($aEvents as $aEvent)
	            			$this->_oModule->deleteEvent($aEvent);
            		}
            		break;
            	}

            	$aHandlers = $this->_oModule->_oDb->getHandlers(array('type' => 'by_group_key_type', 'group' => $aHandler['group']));

            	$aEvent = $this->_oModule->_oDb->getEvents(array(
            		'browse' => 'descriptor', 
            		'type' => $oAlert->sUnit,
            		'action' => $aHandlers[BX_BASE_MOD_NTFS_HANDLER_TYPE_INSERT]['alert_action'], 
            		'object_id' => $oAlert->iObject
            	));
            	$this->_oModule->deleteEvent($aEvent);
                break;
        }
    }
}

/** @} */
