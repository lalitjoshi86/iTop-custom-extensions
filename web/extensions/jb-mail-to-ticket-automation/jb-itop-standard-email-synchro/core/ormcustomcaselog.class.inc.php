<?php

/**
 * @copyright   Copyright (C) 2019 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     2019-08-11 20:40:30
 *
 * Custom version of ormCaseLog.
 * Extended AddLogEntry() to support on_behalf_of_user_id (rather than just 'on_behalf_of'). 
 * Important: NOT implemented so for AddLogEntryFromJSON(). If needed, add this.
 *
 * Originally planned an UpdateLogEntryAt() to support retro-active updating (meant for custom version of 'Mail to Ticket Automation'); 
 * but the method would have to loop over each entry every time anyway.
 * Therefore, it's better to build an ormCustomCaseLog from scratch and use AddLogEntry()
 */
 
namespace jb_mail_to_ticket_automation;

use \AttributeDateTime;
use \ormCaseLog;
use \HTMLSanitizer;	   
use \UserRights;

class ormCustomCaseLog extends ormCaseLog {

	/**
	 * Add a new entry to the log or merge the given text into the currently modified entry 
	 * and updates the internal index
	 * @param string $sText The text of the new entry
	 * @param string $sOnBehalfOf  Custom specified user name (for example: "from:" in the Mail to Ticket Automation extension)
	 * @param integer|null $iOnBehalfOfUserId Custom specified user ID
	 * @param String $sDateTime Time sent
	 */
	public function AddLogEntry($sText, $sOnBehalfOf = '', $iOnBehalfOfUserId = null, $sDateTime = '')
	{
		$sText = HTMLSanitizer::Sanitize($sText);
		$sDateTime = ($sDateTime == '' ? date(AttributeDateTime::GetInternalFormat()) : date(AttributeDateTime::GetInternalFormat(), strtotime($sDateTime)));
		
		$iUserId = null;
		
		if ($sOnBehalfOf == '')	{
			// Not specified. Fall back to current user.
			$sOnBehalfOf = UserRights::GetUserFriendlyName();
			
			if($iOnBehalfOfUserId === null) {
				// Not specified. Fall back co current user's ID
				$iUserId = UserRights::GetUserId();
			}
		}
		
		/* 
			No prepending!
			if ($this->m_bModified)
			{
				$aLatestEntry = end($this->m_aIndex);
				if ($aLatestEntry['user_name'] == $sOnBehalfOf && $aLatestEntry['user_id'] == $iUserId)
				{
					// Append the new text to the previous one
					$sPreviousText = substr($this->m_sLog, $aLatestEntry['separator_length'], $aLatestEntry['text_length']);
					$sText = $sPreviousText."\n".$sText;

					// Cleanup the previous entry
					array_pop($this->m_aIndex);
					$this->m_sLog = substr($this->m_sLog, $aLatestEntry['separator_length'] + $aLatestEntry['text_length']);
				}
			}
		*/

		$sSeparator = sprintf(CASELOG_SEPARATOR, $sDateTime, $sOnBehalfOf, $$iOnBehalfOfUserId);
		$iSepLength = strlen($sSeparator);
		$iTextlength = strlen($sText);
		
		// Not looking to add duplicate entries, so
		$aEntry =  array(
			'user_name' => $sOnBehalfOf,
			'user_id' => $$iOnBehalfOfUserId,
			'date' => strtotime($sDateTime),
			'text_length' => $iTextlength,
			'separator_length' => $iSepLength,
			'format' => 'html',
		);
		
		if(in_array($aEntry, $this->m_aIndex) == false) {
		
			$this->m_sLog = $sSeparator.$sText.$this->m_sLog; // Latest entry printed first
			$this->m_aIndex[] = $aEntry;
			$this->m_bModified = true;
			
		}
		
	}
	
	/**
	 * Adds case log entries from a provided source ormCaseLog
	 *
	 * @param ormCaseLog $oSourceCaseLog Case log
	 *
	 * @return void
	 */
	public function AddLogEntriesFromCaseLog($oSourceCaseLog) {
		
		foreach($oSourceCaseLog->GetAsArray() as $aEntry) {
			
			// CustomCaseLog's AddLogEntry remains flexible; keeps original user information and datetime
			$this->AddLogEntry( $aEntry['message_html'], $aEntry['user_login'], $aEntry['user_id'], $aEntry['date'] );
			
		}
		
	}
	
	/**
	 * Returns entries
	 * 
	 * @return Array
	 */
	public function GetEntries() {
		return $this->m_aIndex;
	}
	
	
	
	/**
	 * Sorts case log entries by timestamp ('date')
	 *
	 * @param Boolean $bAscending Defaults to true.
	 *
	 * @return void
	 *
	 * @details Warning: if DBUpdate() is called AFTER this, it will be considered a modification and it will be likely be logged as 'new entry added'
	 */
	public function ToSortedCaseLog($bAscending = true) {
		
		$aEntries = $this->GetAsArray();
		
		usort($aEntries, function ($item1, $item2) use ($bAscending) {
			
			$dtCompare1 = strtotime($item1['date']);
			$dtCompare2 = strtotime($item2['date']);
			
			return (($dtCompare1 <=> $dtCompare2) * ( $bAscending == true ? 1 : -1));
		});
		
		// m_aIndex AND m_sLog both need to be updated, hence this trick.
		$oCustomCaseLog = new \jb_mail_to_ticket_automation\ormCustomCaseLog();
		
		// The order above might be descending, as wanted.
		// However, if that item gets added first, iTop will add the subsequent (older) issues on top of that entry again.
		foreach(array_reverse($aEntries) as $aEntry) {
			$oCustomCaseLog->AddLogEntry($aEntry['message_html'], $aEntry['user_login'], $aEntry['user_id'], $aEntry['date']);
		}
		
		return $oCustomCaseLog;
		
	}
	
}
	
