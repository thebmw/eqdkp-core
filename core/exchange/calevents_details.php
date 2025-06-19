<?php
/*	Project:	EQdkp-Plus
 *	Package:	EQdkp-plus
 *	Link:		http://eqdkp-plus.eu
 *
 *	Copyright (C) 2006-2016 EQdkp-Plus Developer Team
 *
 *	This program is free software: you can redistribute it and/or modify
 *	it under the terms of the GNU Affero General Public License as published
 *	by the Free Software Foundation, either version 3 of the License, or
 *	(at your option) any later version.
 *
 *	This program is distributed in the hope that it will be useful,
 *	but WITHOUT ANY WARRANTY; without even the implied warranty of
 *	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *	GNU Affero General Public License for more details.
 *
 *	You should have received a copy of the GNU Affero General Public License
 *	along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

if (!defined('EQDKP_INC')){
	die('Do not access this file directly.');
}

if (!class_exists('exchange_calevents_details')){
	class exchange_calevents_details extends gen_class {
		public static $shortcuts = array('pex'=>'plus_exchange');

		public function get_calevents_details($params, $arrBody){
			$isAPITokenRequest = $this->pex->getIsApiTokenRequest();

			if ($isAPITokenRequest || $this->user->check_auth('po_calendarevent', false)){
				if ( intval($params['get']['eventid']) > 0){
					$event_id = intval($params['get']['eventid']);
					$eventdata	= $this->pdh->get('calendar_events', 'data', array($event_id));
					$comments = $this->pdh->get('comment', 'filtered_list', array('articles', '12_'.$event_id));
					
					$intComments = 0;
					if (is_array($comments)){
						foreach($comments as $key => $row){

							$arrReplies = array();
							if(count($row['replies'])){
								foreach($row['replies'] as $com){
									$avatarimg = $this->pdh->get('user', 'avatarimglink', array($com['userid']));
									$arrReplies['comment:'.$com['id']] = array(
											'username'			=> unsanitize($com['username']),
											'user_avatar'		=> $this->pfh->FileLink((($avatarimg != "") ? $avatarimg : 'images/global/avatar-default.svg'), false, 'absolute'),
											'date'				=> $this->time->date('Y-m-d H:i', $com['date']),
											'date_timestamp'	=> $com['date'],
											'message'			=> $this->bbcode->toHTML($com['text']),
											'comment_id'		=> $com['id'],
											'reply_to'			=> $key,
									);
									$intComments++;
								}
							}


							$avatarimg = $this->pdh->get('user', 'avatarimglink', array($row['userid']));
							$arrComments['comment:'.$key] = array(
								'username'			=> $row['username'],
								'user_avatar'		=> $this->pfh->FileLink((($avatarimg != "") ? $avatarimg : 'images/global/avatar-default.svg'), false, 'absolute'),
								'date'				=> $this->time->date('Y-m-d H:i', $row['date']),
								'date_timestamp'	=> $row['date'],
								'message'			=> $this->bbcode->toHTML($row['text']),
								'comment_id'		=> $key,
								'replies'			=> $arrReplies,
							);
							$intComments++;
						}
					}

					$raidmode		= ((int)$this->pdh->get('calendar_events', 'calendartype', array($event_id)) == 1) ? true : false;
					if ($raidmode) {

						// get the memners
						$notsigned_filter		= $this->config->get('calendar_raid_nsfilter');
						$this->members			= $this->pdh->maget('member', array('userid', 'name', 'classid'), 0, array($this->pdh->sort($this->pdh->get('member', 'id_list', array(
														((in_array('inactive', $notsigned_filter)) ? false : true),
														((in_array('hidden', $notsigned_filter)) ? false : true),
														((in_array('special', $notsigned_filter)) ? false : true),
														((in_array('twinks', $notsigned_filter)) ? false : true),
													)), 'member', 'classname')));

						// get all attendees
						$this->attendees_raw	= $this->pdh->get('calendar_raids_attendees', 'attendees', array($event_id));
						$attendeeids = (is_array($this->attendees_raw)) ? array_keys($this->attendees_raw) : array();
						$this->unsigned = $this->members;
						foreach($attendeeids as $mattid){
							$att_userid = $this->pdh->get('member', 'userid', array($mattid));
							$filter_attuserids = $this->pdh->get('member', 'connection_id', array($att_userid));
							if(is_array($filter_attuserids)){
								foreach($filter_attuserids as $attmemid){
									if($this->pdh->get('calendar_raids_attendees', 'status', array($event_id, $attmemid)) != 4){
										unset($this->unsigned[$attmemid]);
									}
								}
							}
						}

						// Guests / rest
						$this->guests			= $this->pdh->get('calendar_raids_guests', 'members', array($event_id));
						$this->raidcategories	= ($eventdata['extension']['raidmode'] == 'role') ? $this->pdh->aget('roles', 'name', 0, array($this->pdh->get('roles', 'id_list'))) : $this->game->get_primary_classes(array('id_0'));
						$this->mystatus			= $this->pdh->get('calendar_raids_attendees', 'myattendees', array($event_id, $this->user->id));

						// Build the attendees aray for this raid by class
						if(is_array($this->attendees_raw)){
							$this->attendees = $this->attendees_count = array();
							foreach($this->attendees_raw as $attendeeid=>$attendeedata){
								$attclassid = (isset($eventdata['extension']['raidmode']) && $eventdata['extension']['raidmode'] == 'role') ? $attendeedata['member_role'] : $this->pdh->get('member', 'classid', array($attendeeid));
								$role_class = (($eventdata['extension']['raidmode'] == 'role') ? $attendeedata['member_role'] : $attclassid);
								$this->attendees[$attendeedata['signup_status']][$role_class][$attendeeid] = $attendeedata;
								$this->attendees_count[$attendeedata['signup_status']][$attendeeid] = true;
							}
						}else{
							$this->attendees = array();
						}
						
						// raid guests
						$arrGuests = array();
						if(is_array($this->guests) && count($this->guests) > 0){
							foreach($this->guests as $guestid=>$guestsdata){
								$role_class = (($eventdata['extension']['raidmode'] == 'role') ? $guestsdata['role'] : $guestsdata['class']);
								
								$arrGuests[$guestsdata['status']][$role_class][] = array(
										'id'		=> $guestid,
										'name'		=> unsanitize($guestsdata['name']),
										'classid'	=> $guestsdata['class'],
										'class'		=> $this->game->get_name('primary', $guestsdata['class']),
										#'status'	=> $guestsdata['status'],
										'note'		=> $guestsdata['note'],
										'guest'		=> 1,
								);
							}
						}

						//The Status & Member data
						$raidcal_status = $this->config->get('calendar_raid_status');
						$this->raidstatus_full = $this->raidstatus = array();
						if(is_array($raidcal_status)){
							foreach($raidcal_status as $raidcalstat_id){
								if($raidcalstat_id != 4){	// do not use the not signed members
									$this->raidstatus[$raidcalstat_id]	= $this->user->lang(array('raidevent_raid_status', $raidcalstat_id));
								}
								$this->raidstatus_full[$raidcalstat_id]	= $this->user->lang(array('raidevent_raid_status', $raidcalstat_id));
							}
						}
						$arrStatus = array();
						foreach($this->raidstatus as $statuskey=>$statusname){

							$arrClasses = array();
							foreach ($this->raidcategories as $classid=>$classname){
								// The characters
								$arrChars = array();

								if(isset($this->attendees[$statuskey][$classid]) && is_array($this->attendees[$statuskey][$classid])){
									foreach($this->attendees[$statuskey][$classid] as $memberid=>$memberdata){
										//$shownotes_ugroups = $this->acl->get_groups_with_active_auth('u_calendar_raidnotes');
										$arrData = $this->pdh->get('member', 'profiledata', array($memberid));

										$arrChars['char:'.$memberid] = array(
											'id'			=> $memberid,
											'name'			=> unsanitize($this->pdh->get('member', 'name', array($memberid))),
											'name_export'	=> $this->game->handle_export_charnames($this->pdh->get('member', 'name', array($memberid)), $memberid),
											'classid'		=> $this->pdh->get('member', 'classid', array($memberid)),
											'signedbyadmin'	=> ($memberdata['signedbyadmin']) ? 1 : 0,
											'note'			=> ((trim($memberdata['note']) && $this->user->check_group($shownotes_ugroups, false, $this->user->id)) ? $memberdata['note'] : ''),
											'rank'			=> $this->pdh->get('member', 'rankname', array($memberid)),
											'profiledata'	=> $arrData,
											'guest'			=> 0,
										);

									}
								}
								
								if(isset($arrGuests[$statuskey][$classid])){
									foreach($arrGuests[$statuskey][$classid] as $arrGuest){
										$arrChars['guest:'.$arrGuest['id']] = $arrGuest;
										$this->attendees_count[$statuskey]++;
									}
								}

								$arrClasses["category".$classid] = array(
									'id'		=> $classid,
									'name'		=> $classname,
									'color'		=> ($eventdata['extension']['raidmode'] != 'role') ? $this->game->get_class_color($classid) : '',
									'count'		=> count($arrChars),
									'maxcount'	=> ($eventdata['extension']['raidmode'] == 'none' && $eventdata['extension']['distribution'][$classid] == 0) ? '' : $eventdata['extension']['distribution'][$classid],
									'chars'		=> $arrChars,
								);
							}

							$arrStatus['status'.$statuskey] = array(
								'id'		=> $statuskey,
								'name'		=> $statusname,
								'count'		=> (isset($this->attendees_count[$statuskey])) ? count($this->attendees_count[$statuskey]) : 0,
								'maxcount'	=> $eventdata['extension']['attendee_count'],
								'categories'=> $arrClasses,
							);

						}


						//UserChars
						$user_chars = $this->pdh->aget('member', 'name', 0, array($this->pdh->get('member', 'connection_id', array($this->user->id))));
						$mainchar = $this->pdh->get('user', 'mainchar', array($this->user->id));
						$arrRoles = array();
						if (is_array($user_chars)){
							foreach ($user_chars as $key=>$charname){
								$roles = $this->pdh->get('roles', 'memberroles', array($this->pdh->get('member', 'classid', array($key))));
								if (is_array($roles)){
									$arrRoles = array();
									foreach ($roles as $roleid => $rolename){
										$arrRoles['role:'.$roleid] = array(
											'id'	=> $roleid,
											'name'	=> $rolename,
											'signed_in'	=> ($this->mystatus['member_role'] == $roleid) ? 1 : 0,
										);
									}
								}

								$arrData = $this->pdh->get('member', 'profiledata', array($key));

								$arrUserChars['char:'.$key] = array(
									'id'		=> $key,
									'name'		=> unsanitize($charname),
									'name_export'=> $this->game->handle_export_charnames($charname, $key),
									'signed_in'	=> ($this->mystatus['member_id'] == $key) ? 1 : 0,
									'main'		=> ($key == $mainchar) ? 1 : 0,
									'class'		=> $this->pdh->get('member', 'classid', array($key)),
									'roles'		=> $arrRoles,
									'raidgroup' => $this->pdh->get('calendar_raids_attendees', 'raidgroup', array($event_id, $key)),
									'profiledata'=> $arrData,
								);
							}
						}

						$userstatus['status'] = (!strlen($this->mystatus['signup_status'])) ? -1 : $this->mystatus['signup_status'];
						$userstatus['status_name'] = ($this->mystatus['signup_status'] >= 0) ? $this->raidstatus[$this->mystatus['signup_status']] : '';

						if ($userstatus['status'] > -1){
							$userstatus['char_id'] = $this->mystatus['member_id'];
							$userstatus['char_class'] = $this->pdh->get('member', 'classid', array($this->mystatus['member_id']));
							$userstatus['char_name'] = $this->pdh->get('member', 'name', array($this->mystatus['member_id']));
							$userstatus['raidgroup'] = $this->pdh->get('calendar_raids_attendees', 'raidgroup', array($event_id, $this->mystatus['member_id']));
							if ($this->mystatus['member_role'] > 0 ) $userstatus['char_roleid'] = $this->mystatus['member_role'];
							if ($this->mystatus['member_role'] > 0 ) $userstatus['char_role'] = $this->pdh->get('roles', 'name', array($this->mystatus['member_role']));
						}

						$arrCommentsOut = array(
							'count' => $intComments,
							'page'	=> 'articles',
							'attachid' => '12_'.$event_id,
							'comments' => $arrComments,
						);

						$arrRaidgroups = array();
						foreach ($this->pdh->aget('raid_groups', 'name', false, array($this->pdh->get('raid_groups', 'id_list'))) as $raidgroupid => $raidgroupname){
							$arrRaidgroups['raidgroup:'.$raidgroupid] = array(
								'id'		=> $raidgroupid,
								'name'		=> $raidgroupname,
								'default'	=> ($this->pdh->get('raid_groups', 'standard', array($raidgroupid))) ? 1 : 0,
								'color'		=> $this->pdh->get('raid_groups', 'color', array($raidgroupid)),
							);
						}



						$out = array(
							'type'			=> ($raidmode == 'raid') ? 'raid' : 'event',
							'categories'	=> ($eventdata['extension']['raidmode'] == 'role') ? 'roles' : 'classes',
							'title' 		=> unsanitize($this->pdh->get('calendar_events', 'name', array($event_id))),
							'start'			=> $this->time->date('Y-m-d H:i', $this->pdh->get('calendar_events', 'time_start', array($event_id))),
							'start_timestamp'=> $this->pdh->get('calendar_events', 'time_start', array($event_id)),
							'end'			=> $this->time->date('Y-m-d H:i', $this->pdh->get('calendar_events', 'time_end', array($event_id))),
							'end_timestamp'	=> $this->pdh->get('calendar_events', 'time_end', array($event_id)),
							'deadline'		=> $this->time->date('Y-m-d H:i', $eventdata['timestamp_start']-($eventdata['extension']['deadlinedate'] * 3600)),
							'deadline_timestamp'=> $eventdata['timestamp_start']-($eventdata['extension']['deadlinedate'] * 3600),
							'allDay'		=> ($this->pdh->get('calendar_events', 'allday', array($event_id)) > 0) ? 1 : 0,
							'closed'		=> ($this->pdh->get('calendar_events', 'raidstatus', array($event_id)) == 1) ? 1 : 0,
							'icon'			=> ($eventdata['extension']['raid_eventid']) ? $this->pdh->get('event', 'icon', array($eventdata['extension']['raid_eventid'], true)) : '',
							'note'			=> unsanitize($this->bbcode->remove_bbcode($this->pdh->get('calendar_events', 'notes', array($event_id, true)))),
							'raidleader'	=> unsanitize(($eventdata['extension']['raidleader'] > 0) ? implode(', ', $this->pdh->aget('member', 'name', 0, array($eventdata['extension']['raidleader']))) : ''),
							'raidstatus'	=> $arrStatus,
							'user_status'	=> $userstatus,
							'user_chars'	=> $arrUserChars,
							'comments'		=> $arrCommentsOut,
							'calendar'		=> $eventdata['calendar_id'],
							'calendar_name'	=> $this->pdh->get('calendar_events', 'calendar', array($event_id)),
							'raidgroups'	=> $arrRaidgroups,
							'url'			=> $this->env->buildlink(false).register('routing')->build('calendarevent', $this->pdh->get('calendar_events', 'name', array($event_id)), $event_id, false),
						);
					} else {
						//Check if private
						if(!$this->pdh->get('calendar_events', 'private_userperm', array($event_id))){
							return $this->pex->error('access denied');
						}
						
						$mystatus = -1;					
						
						// invited attendees
						$event_invited		= (isset($eventdata['extension']['invited']) && count($eventdata['extension']['invited']) > 0) ? $eventdata['extension']['invited'] : array();
						if(count($event_invited) > 0){
							foreach($event_invited as $inviteddata){
								$userstatus[4]['user:'.$inviteddata] = array(
										'userid'	=> $inviteddata,
										'name'		=> $this->pdh->get('user', 'name', array($inviteddata)),
										'joined'	=> $this->pdh->get('calendar_events', 'joined_invitation', array($event_id, $inviteddata)),
								);
								
								if($inviteddata == $this->user->id) $mystatus = 4;
							}
						}
						
						// attending users
						$event_attendees		= (isset($eventdata['extension']['attendance']) && count($eventdata['extension']['attendance']) > 0) ? $eventdata['extension']['attendance'] : array();
						if(count($event_attendees) > 0){
							foreach($event_attendees as $attuserid=>$attstatus){
								$attendancestatus			= $this->statusID2status($attstatus);
								$userstatus[$attstatus]['user:'.$attuserid] = array(
										'userid'	=> $attuserid,
										'name'		=> $this->pdh->get('user', 'name', array($attuserid)),
										'joined'	=> false,
								);
								
								if($attuserid == $this->user->id) $mystatus = $attstatus;
							}
						}
						
						$arrAvailableStatus = array(1=>'attendance',2=>'maybe',3=>'decline',4=>'invited');
						$arrOutStatus = array();
						foreach($arrAvailableStatus as $key => $val){
							$strStatus = $this->statusID2status($key);
							$arrOutStatus['status:'.$key] = array(
								'id'	=> $key,
								'name'	=> (($key == 4) ? 'Invited' : $this->user->lang('calendar_eventdetails_'.$strStatus)),
								'count' => count($userstatus[$key]),
								'users' => $userstatus[$key],
							);
						}
						
						$out = array(
							'type'			=> ($raidmode == 'raid') ? 'raid' : 'event',
							'title' 		=> unsanitize($this->pdh->get('calendar_events', 'name', array($event_id))),
							'start'			=> $this->time->date('Y-m-d H:i', $this->pdh->get('calendar_events', 'time_start', array($event_id))),
							'start_timestamp'=> $this->pdh->get('calendar_events', 'time_start', array($event_id)),
							'end'			=> $this->time->date('Y-m-d H:i', $this->pdh->get('calendar_events', 'time_end', array($event_id))),
							'end_timestamp'	=> $this->pdh->get('calendar_events', 'time_end', array($event_id)),
							'allDay'		=> ($this->pdh->get('calendar_events', 'allday', array($event_id)) > 0) ? 1 : 0,
							'note'			=> unsanitize($this->bbcode->remove_bbcode($this->pdh->get('calendar_events', 'notes', array($event_id, true)))),
							'calendar'		=> $eventdata['calendar_id'],
							'calendar_name'	=> $this->pdh->get('calendar_events', 'calendar', array($event_id)),
							'icon'			=> $this->pdh->get('calendar_events', 'event_icon', array($event_id)),
							'location'		=> $eventdata['extension']['location'],
							'location-lat'	=> $eventdata['extension']['location-lat'],
							'location-lon'	=> $eventdata['extension']['location-lon'],
							'attendees'		=> $arrOutStatus,
							'user_status'	=> $mystatus,
							'url'			=> $this->env->buildlink(false).register('routing')->build('calendarevent', $this->pdh->get('calendar_events', 'name', array($event_id)), $event_id, false),
						);
						

						
					}
					return $out;
				} else {
					return $this->pex->error('no eventid given');
				}
			} else {
				return $this->pex->error('access denied');
			}

		}
		
		private function statusID2status($status){
			$attendancestatus = "unknown";
			switch($status){
				case 1:		$attendancestatus = 'confirmations'; break;
				case 2:		$attendancestatus = 'maybes'; break;
				case 3:		$attendancestatus = 'declines'; break;
			}
			return $attendancestatus;
		}
	}
}
