<?php if (!defined('BASEPATH')) exit('No direct script access allowed');
/**
 * CodeIgniter Processing (Formatting & Validating) the Fetched Lead data Class
 *
 * user text variables relates mongo->leads & lead text variables relates mysql->lead_info
 *
 * @package	CodeIgniter
 * @category	Libraries
 * @author	Rohit Patil (rohitpatil30) @ Codaemon Softwares, Pune
 */

class Lead_registration_process {

    private $CI;
    private $country;
    private $result;
    private $process_log;
    private $final_reason;
    private $simple_msg;

    //private $lead_data_fields;
    private $data_fields;
    private $user_data_fields;
    private $user_history_fields;
    private $sweepstake_fields;
    private $optin_fields;
    private $single_fields;

    private $gender_code;
    private $gender_value;
    private $mandatory_fields;
    private $constants;
    private $session_id;

    private $doi_email_after_valid_acceptance;
    private $mmpg_validation;
    private $update_accepted_lead_as_valid;
    private $beyond_registration;
    private $deduct_marketing_caps_after_acceptance;
    private $reject_duplicate;
    private $allowed_phone_digits;
    private $area_code_check;
    private $allowed_area_code_digits;
    private $area_code_not_allowed;
    private $landline_access_codes;
    private $mobile_access_codes;
    private $country_code_check;
    private $country_code;
    private $fields_validation;

    function __construct($data=array()) {
        $country=isset($data['country']) ? $data['country'] : "";
        if($country!='') {
            $this->CI = & get_instance();
            $this->country=$country;
            $this->read_db = $this->CI->load->database($this->CI->config->item('read',$country), TRUE);
            $this->write_db = $this->CI->load->database($this->CI->config->item('write',$country), TRUE);
            $this->mongo_db = new Mongo_db($country.'_mongo');

            $this->CI->load->model('sweepstakes/sweepstake_model');
            $this->CI->sweepstake_model->country_construct($this->country);
            $this->CI->load->model('global_management_model');
            $this->CI->global_management_model->country_construct($this->country);
            $email_params=array('country'=>$this->country);
            $this->CI->load->library('custom_email',$email_params);

            $lead_processing=isset($data['lead_processing']) ? $data['lead_processing'] : false;
            if($lead_processing) {
                $leads=isset($data['leads']) ? $data['leads'] : array();
                if(!empty($leads)) {
                    $this->CI->load->model('lead_model');
                    $this->CI->lead_model->country_construct($this->country);
                    $this->CI->load->model('auto_correct_model');
                    $this->CI->auto_correct_model->country_construct($this->country);
                    $this->CI->load->model('badword_model');
                    $this->CI->badword_model->country_construct($this->country);
                    $this->CI->load->model('blacklist_model');
                    $this->CI->load->model('marketing/marketing_model');
                    $this->CI->marketing_model->country_construct($this->country);
                    $this->CI->load->library('lead_validator');
                    $this->CI->lead_validator->country_construct($this->country);
                    $this->CI->load->library("kickbox");

                    $this->data_fields = array('salutation','first_name','last_name','email','date_of_birth','ip_address','registration_date','house_number','street','city','post_code','area_code','phone');
                    $this->user_data_fields = array('area_code','salutation','date_of_birth' ,'first_name','last_name','email','phone','street','post_code','place','country','city','house_number','registration_type');
                    $this->user_history_fields=array('sweepstake_id','sweepstake_url','session_id','salutation','date_of_birth','update_reason','agree','email_optin_flag','email_oi_timestamp','phone_optin_flag','phone_oi_timestamp','post_code_optin_flag','post_code_oi_timestamp','double_optin_flag',
                        'last_user_ip','double_oi_ip','double_oi_timestamp','last_update_source','registration_type','current_status','single_optin_flag','single_oi_ip','operating_system','browser_version','browser_language','sub_domain_name','publisher_id','campaign_id','marketing_partner_id','suspicious_flag','last_action'
                    );

                    $this->single_fields = array('salutation','date_of_birth_day','date_of_birth_day','date_of_birth_month','date_of_birth_year','date_of_birth','post_code_optin_flag','phone_optin_flag','registration_type','email_optin_flag','phone_oi_timestamp','post_code_oi_timestamp','double_optin_flag','double_oi_timestamp','double_oi_ip','area_code','free_field_1','free_field_2','free_field_3','free_field_4','free_field_5');

                    $this->gender_code = array('mr'=>'male','sir'=>'male','herr'=>'male',
                        'mrs'=>'female','miss'=>'female','ms'=>'female','dame'=>'female','madam'=>'female',"ma'am"=>'female','frau'=>'female','Fehlschuss'=>'female',
                    );
                    $this->gender_value = array(
                        'male'=>array('australia'=>'mr','germany'=>'herr','uk'=>'mr'),
                        'female'=>array('australia'=>'mrs','germany'=>'frau','uk'=>'mrs')
                    );
                    $this->constants=isset($data['constants']) ? $data['constants'] : array();

                    $default_mandatory_fields = array('salutation','first_name','last_name','email','date_of_birth');
                    $this->mandatory_fields = isset($data['mandatory_fields']) ? $data['mandatory_fields'] : $default_mandatory_fields;
                    $this->doi_email_after_valid_acceptance=isset($data['doi_email_after_valid_acceptance']) ? $data['doi_email_after_valid_acceptance'] : true;
                    $this->mmpg_validation=isset($data['mmpg_validation']) ? $data['mmpg_validation'] : false;
                    $this->update_accepted_lead_as_valid=isset($data['update_accepted_lead_as_valid']) ? $data['update_accepted_lead_as_valid'] : true;
                    $this->beyond_registration=isset($data['beyond_registration']) ? $data['beyond_registration'] : true;
                    $this->deduct_marketing_caps_after_acceptance=isset($data['deduct_marketing_caps_after_acceptance']) ? $data['deduct_marketing_caps_after_acceptance'] : true;
                    $this->reject_duplicate=isset($data['reject_duplicate']) ? $data['reject_duplicate'] : true;

                    if(isset($data['phone_type']) && !empty($data['phone_type'])) {
                        $this->allowed_phone_digits=isset($data['phone_type']['allowed_phone_digits']) ? $data['phone_type']['allowed_phone_digits'] : '';
                        $this->area_code_check=isset($data['phone_type']['area_code_check']) ? $data['phone_type']['area_code_check'] : false;
                        $this->allowed_area_code_digits=isset($data['phone_type']['allowed_area_code_digits']) ? $data['phone_type']['allowed_area_code_digits'] : '';
                        $this->area_code_not_allowed=isset($data['phone_type']['area_code_not_allowed']) ? $data['phone_type']['area_code_not_allowed'] : array();
                        $this->landline_access_codes=isset($data['phone_type']['landline_access_codes']) ? $data['phone_type']['landline_access_codes'] : array();
                        $this->mobile_access_codes=isset($data['phone_type']['mobile_access_codes']) ? $data['phone_type']['mobile_access_codes'] : array();
                        $this->country_code_check=isset($data['phone_type']['country_code_check']) ? $data['phone_type']['country_code_check'] : false;
                        $this->country_code=isset($data['phone_type']['country_code']) ? $data['phone_type']['country_code'] : '';
                    }
                    $this->fields_validation=isset($data['fields_validation']) ? $data['fields_validation'] : array();

                    //Rohit=> Calling the actual lead Processing function
                    $this->lead_data_fetch($leads);
                } else {
                    $reason='No Data Found..!';
                    $this->result['status']=false;
                    $this->result['msg']=$reason;
                    $this->process_log[]=array('reason'=>$reason);
                    $this->final_reason=$reason;
                    $this->simple_msg=$reason;
                }
            }
        } else {
            $reason='No Country Found..!';
            $this->result['status']=false;
            $this->result['msg']=$reason;
            $this->process_log[]=array('reason'=>$reason);
            $this->final_reason=$reason;
            $this->simple_msg=$reason;
        }
    } //Rohit=> __construct() Ends

    //Rohit=> Lead data Fetching from Third Party API
    private function lead_data_fetch($leads=array()) {
        $final_reason=$simple_msg='';
        $result=$api_log=$lead_result=$fields_validation_rule=array();
        $auto_correction_rules=$this->CI->auto_correct_model->get_active_autocorrect();
        $badword=$this->CI->badword_model->get_active_badwords();
        $combo_badword=$this->CI->badword_model->get_active_combo_badwords();
        $sr=0;
        foreach($leads as $post_lead) {
            $lead_status=$lead_msg=$reason=$tmp_reason=$lead_id='';
            $log_email=isset($post_lead['email']) ? $post_lead['email'] : 'undefined';
            $this->session_id=((isset($post_lead['session_id']) && trim($post_lead['session_id'])!='') ? trim($post_lead['session_id']) : substr(sha1(rand()),0,16));
            $unique_id=(isset($post_lead['unique_id']) && trim($post_lead['unique_id'])!='' && trim($post_lead['unique_id'])!='0') ? trim($post_lead['unique_id']) : $this->session_id;
            $api_log[$sr]=array('unique_id'=>$unique_id,'lead_id'=>'','email'=>$log_email,'marketing_partner_id'=>'','marketing_campaign_id'=>'','api_response'=>'','mandatory_flag'=>'0','duplicate_flag'=>'0','validation_flag'=>'0','blacklist_flag'=>'0','badword_flag'=>'0','ses_bounce_flag'=>'0','mmpg_flag'=>'0','reason'=>'','plausibility_count'=>'0','is_valid'=>'0');

            //Rohit=> Formatting the Fetched Values Properly & arranging into mongo Structure
            $format_data=$this->formatting_data($post_lead,$auto_correction_rules);

            if($format_data['status']) { //Rohit=> Formatting of data is completed
                $user_data=$format_data['user_data'];
                $lead_data=$format_data['lead_data'];
                $api_log[$sr]['mandatory_flag']=2;
                if(isset($lead_data['marketing_partner_id'])) $api_log[$sr]['marketing_partner_id']=$lead_data['marketing_partner_id'];
                if(isset($lead_data['marketing_campaign_id'])) $api_log[$sr]['marketing_campaign_id']=$lead_data['marketing_campaign_id'];
                $sweepstake_id=isset($lead_data['sweepstake_id']) ? $lead_data['sweepstake_id'] : 0;

                //Rohit=> Collecting the Validations Rules of Fields assigned in Sweepstake
                if($sweepstake_id>0) {
                    if(!(array_key_exists($sweepstake_id,$fields_validation_rule))) {
                        $sweepstake_fields_validation=$this->CI->sweepstake_model->get_sweepstake_backend_field_details($sweepstake_id);
                        $temp_field_validate=array();
                        foreach($sweepstake_fields_validation as $temp_value) {
                            $temp_field=isset($temp_value['field_value']) ? $temp_value['field_value'] : "";
                            if($temp_field!='') {
                                $temp_field_validate[$temp_field]=array(
                                    'min_length'=>isset($temp_value['min_length']) ? $temp_value['min_length'] : "",
                                    'max_length'=>isset($temp_value['max_length']) ? $temp_value['max_length'] : "",
                                    'start_with'=>isset($temp_value['start_with']) ? $temp_value['start_with'] : ""
                                );
                            }
                        }
                        $fields_validation_rule[$sweepstake_id]=$temp_field_validate;
                    }
                }
                //Rohit=> If Sweepstake Validation rules are empty then collecting the Field's Validation Rules from config file
                if(empty($fields_validation_rule) || (isset($fields_validation_rule[$sweepstake_id]) && empty($fields_validation_rule[$sweepstake_id]))) {
                    $fields_validation_rule[$sweepstake_id]=$this->fields_validation;
                }
                //Rohit=> Checking the Record values with Validation Rules
                $validation_result=$this->validating_data($user_data,$fields_validation_rule[$sweepstake_id]);
                $api_log[$sr]['validation_flag']=2;
                if(isset($validation_result['check']) && $validation_result['check']==true) {
                    //Rohit=> Checking for Record duplication if already exists
                    $duplicate_result=$this->user_exists($user_data);
                    $duplicate_check=isset($duplicate_result['status']) ? $duplicate_result['status'] : false;
                    if($this->reject_duplicate && $duplicate_check) {
                        $api_log[$sr]['duplicate_flag']=1;
                        $lead_status=false;
                        $lead_msg='Record Rejected as Found Duplicate..!';
                        $reason='Record Found Duplicate..!..!';
                    } else {
                        $plausibility_count=$total_email_ids=0;
                        $is_user_deleted=$is_user_unsubscribed=false;
                        if($duplicate_check && isset($duplicate_result['user_id']) && $duplicate_result['user_id']!='') {
                            $api_log[$sr]['duplicate_flag']=1;
                            $lead_id=$lead_data['lead_id']=$user_data['user_id']=$duplicate_result['user_id'];
                            $this->update_mongo_lead($user_data);

                            //Rohit=> Duplicate found & so updating previous records duplicate_flag to 1
                            $updt_lead=array('duplicate_flag'=>1);
                            $this->write_db->where('lead_id',$lead_id);
                            $this->write_db->update('lead_info',$updt_lead);

                            $lead_data['duplicate_flag']=2;
                            $total_email_ids=isset($duplicate_result['total_email_ids']) ? $duplicate_result['total_email_ids'] : 0;
                            $is_user_deleted=(isset($duplicate_result['current_status']) && $duplicate_result['current_status']=='deleted') ? true : false;
                            $is_user_unsubscribed=(isset($duplicate_result['current_status']) && $duplicate_result['current_status']=='active' && isset($duplicate_result['unsubscribed']) && $duplicate_result['unsubscribed']=='1') ? true : false;
                            $plausibility_count=$this->CI->lead_model->getPausabiltyCount($user_data);
                        } else { // Lead Duplication Fails
                            $api_log[$sr]['duplicate_flag']=2;
                            $user_id=$this->mongo_db->insert('leads', $user_data);
                            $lead_id=$lead_data['lead_id']=$user_data['user_id']=(string)$user_id;
                        }
                        if($lead_id!='') { //Rohit=> Record Inserted..
                            $api_log[$sr]['lead_id']=$lead_id;
                            $valid=true;
                            $is_valid=$lead_data['is_valid']=0;

                            //Rohit=> Checking if area_code is to consider with phone number value or not
                            if($this->area_code_check) $lead_data['phone']=$lead_data['area_code'].$lead_data['phone'];

                            $this->write_db->insert('lead_info', $lead_data);
                            $lead_info_id=$this->write_db->insert_id();

                            if($duplicate_check && $lead_info_id!='') { //Rohit=> Updating Duplicate Records fields from previous record
                                //Rohit=> Updating the previous duplicate Record's main values to newly Inserted duplicate record
                                $duplicate_cmd="php ".FCPATH."index.php cron update_duplicate_flag_old_record ".$lead_id." ".$lead_info_id." ".$this->country;
                                exec("$duplicate_cmd > /dev/null 2>&1 &", $duparrOutput);
                            }

                            if($valid) {
                                //Rohit=> Blacklist Checking for Record Values
                                $email_domain=isset($user_data['email'][0]['email_id']) ? explode('@',$user_data['email'][0]['email_id']) : '';
                                $email1=isset($email_domain[0]) ? $email_domain[0] : null;
                                $domain1=isset($email_domain[1]) ? $email_domain[1] : null;
                                $blacklist_data[]=$email1.'_'.$domain1;
                                $blacklist_data[]=$domain1;
                                $blacklist_data[]=isset($user_data['last_user_ip']) ? $user_data['last_user_ip'] : 'NA';
                                $blacklist_data[]=isset($user_data['phone'][0]['number']) ? $user_data['phone'][0]['number'] : null;
                                $blacklist_data[]=isset($user_data['marketing_partner_id']) ? $user_data['marketing_partner_id'] : '';;
                                $blacklist_data['user_id']=$user_data['user_id'];
                                $blacklist_rules=$this->CI->blacklist_model->get_active_blacklist($blacklist_data);
                                $api_log[$sr]['blacklist_flag']=2;
                                if(!empty($blacklist_rules)) {
                                    $blacklist_result=$this->CI->lead_validator->blacklist_validate($user_data,$blacklist_rules);
                                    if(!empty($blacklist_result)) {
                                        $blacklist_status['user_id']=$user_data['user_id'];
                                        $blacklist_status['reason']=$blacklist_result;
                                        $tmp_reason=$this->delete_invalid_lead($user_data,$blacklist_status,'blacklist');
                                        if($reason!='') $reason.=' and '.$tmp_reason;
                                        else $reason=$tmp_reason;
                                        $is_valid=2;
                                        $valid=false;
                                        $api_log[$sr]['blacklist_flag']=1;
                                    }
                                }

                                //Rohit=> Badwords Checking for Record Values
                                if($valid && (!empty($badword) || !empty($combo_badword))) {
                                    $badword_result=$this->CI->lead_validator->badword_validate($user_data,$badword,$combo_badword);
                                    $api_log[$sr]['badword_flag']=2;
                                    if(!empty($badword_result['reason'])) {
                                        $unique_action=array_unique($badword_result['action']);
                                        if(in_array("2",$unique_action)) {
                                            $badword_status['user_id']=$user_data['user_id'];
                                            $badword_status['reason']=$badword_result['reason'];
                                            $tmp_reason=$this->delete_invalid_lead($user_data,$badword_status,'badword');
                                            if($reason!='') $reason.=' and '.$tmp_reason;
                                            else $reason=$tmp_reason;
                                            $is_valid=2;
                                            $valid=false;
                                            $api_log[$sr]['badword_flag']=1;
                                        } else if(in_array("1",$unique_action)) {
                                            if(count($badword_result['reason'])>1) {
                                                $suspitious['user_id']=$user_data['user_id'];
                                                $suspitious['reason']=$badword_result['reason'];
                                                $suspitious['result']['reason']=$badword_result['reason'];
                                                $suspitious['result']['action']=$badword_result['action'][array_search('1', $validationResult['action'])];
                                                $tmp_reason=$this->mark_suspicious($user_data,$suspitious,1);
                                                if($reason!='') $reason.=' and '.$tmp_reason;
                                                else $reason=$tmp_reason;
                                                $is_valid=4;
                                                $valid=false;
                                                $api_log[$sr]['badword_flag']=1;
                                            } else {
                                                $suspitious['user_id']=$user_data['user_id'];
                                                $suspitious['reason']=$badword_result['reason'];
                                                $tmp_reason=$this->mark_suspicious($user_data,$suspitious);
                                                if($reason!='') $reason.=' and '.$tmp_reason;
                                                else $reason=$tmp_reason;
                                                $is_valid=4;
                                                $valid=false;
                                                $api_log[$sr]['badword_flag']=1;
                                            }
                                        }
                                    }
                                }
                            }

                            if($valid) { //Rohit=> After all check's & validations, record found as valid
                                if($duplicate_check) { //Rohit=> If duplicate, then check if record was Deleted or Unsubscribed.
                                    $tmp_reason='';
                                    if($is_user_deleted) { //Rohit=> Marked Deleted Lead as Active
                                        $tmp_reason=$this->activate_lead($user_data,'deleted');
                                    } else if($is_user_unsubscribed) { //Rohit=> Marked Unsubscribed Lead as Active
                                        $tmp_reason=$this->activate_lead($user_data,'unsubscribed');
                                    }
                                    if($tmp_reason!='') {
                                        if($reason!='') $reason.=' and '.$tmp_reason;
                                        else $reason=$tmp_reason;
                                    }

                                    if($reason!='') $reason.=' and Found Duplicate';
                                    else $lead_msg='Found Duplicate and ';
                                }

                                /*//Rohit=> Commented bcoz Not to assign them to sponsoring type campaigns & no processing
                                $sweepstakes_clients=$this->CI->sweepstake_model->get_active_sponsoring_id($sweepstake_id);
                                $active_sponsors=implode(",",$sweepstakes_clients);
                                $this->CI->sweepstake_model->add_active_sponsors($lead_data['lead_id'],$active_sponsors,$user_data['session_id']);
                                */

                                if($this->mmpg_validation) {
                                    //Rohit=> Sending Accepted Record to MMPG for Validation
                                    $mmpg_cmd="php ".FCPATH."index.php mmpg send_mmpg_request ".$lead_id." ".$this->country;
                                    exec("$mmpg_cmd > /dev/null 2>&1 &", $mmpgarrOutput);
                                }
                                if($this->update_accepted_lead_as_valid) {
                                    $lead_updt_data=array();
                                    if($lead_info_id>0) $lead_updt_data['id']=$lead_info_id;
                                    else $lead_updt_data['lead_id']=$lead_id;
                                    $lead_updt_data['is_valid']=$is_valid=1;
                                    $lead_updt_data['mmpg_return_code']=2;
                                    $lead_updt_data['email_optin_flag']=1;
                                    if(!$duplicate_check) $lead_updt_data['mle_flag']=1;
                                    $this->update_lead_info($lead_updt_data);
                                    sleep(1);
                                }
                                if($this->beyond_registration) {
                                    //Rohit=> Sending Accepted Record/User to Beyond Portal
                                    $beyond_cmd="php ".FCPATH."index.php beyond_integration add_user_to_beyond ".$lead_id." ".$this->country;
                                    exec("$beyond_cmd > /dev/null 2>&1 &", $beyondarrOutput);
                                }
                                if($this->deduct_marketing_caps_after_acceptance) {
                                    $marketing_campaign_max_caps_deduction_flag=true;
                                    if($duplicate_check) { //Rohit=> Check if duplicate was been valid or not
                                        $table_name=LEAD_INFO;
                                        $column_name='COUNT('.LEAD_INFO.'.lead_id) as tot_cnt';
                                        $where=array(LEAD_INFO.'.lead_id'=>$lead_id,LEAD_INFO.'.is_valid'=>1,LEAD_INFO.'.duplicate_flag'=>1,LEAD_INFO.'.mmpg_return_code'=>2);
                                        $is_duplicate_lead_valid=get_customised_table_data($country,$table_name,$column_name,$where,array(),array(),array(),'','',array(),'row');
                                        $marketing_campaign_max_caps_deduction_flag=(isset($is_duplicate_lead_valid['tot_cnt']) && $is_duplicate_lead_valid['tot_cnt']>0) ? false : true;
                                    }
                                    if($marketing_campaign_max_caps_deduction_flag) {
                                        $this->CI->marketing_model->deduct_marketing_campaign_max_cap($lead_data['marketing_campaign_id']);
                                    }
                                }
                                if($sweepstake_id>0 && $this->doi_email_after_valid_acceptance) {
                                    //Rohit=> Sending the email (DOI) to the fetched lead.
                                    $total_email_ids+=1;
                                    $sweepstake_url=isset($this->constants['sweepstake_url']) ? $this->constants['sweepstake_url'] : '';
                                    $email_data=array('lead_info_id'=>$lead_info_id,'user_id'=>$lead_id,'email'=>$lead_data['email'],'salutation'=>$lead_data['salutation'],'first_name'=>$lead_data['first_name'],'last_name'=>$lead_data['last_name'],'sweepstake_url'=>$sweepstake_url,'activation_key'=>$user_data['activation_key'],'last_date_modified'=>$user_data['last_date_modified'],'total_email_ids'=>$total_email_ids);
                                    $email_status=false;
                                    $email_status=@$this->send_doi_mail($sweepstake_id,$email_data);
                                    if($email_status==true) $api_log[$sr]['ses_bounce_flag']=2;
                                }
                                //Rohit=>RED-1778 Sending Promotional Emails to All Valid Email Leads
                                $email_lead_data=array('lead_info_id'=>$lead_info_id,'user_id'=>$lead_id,'email'=>$lead_data['email'],'salutation'=>$lead_data['salutation'],'first_name'=>$lead_data['first_name'],'last_name'=>$lead_data['last_name']);
                                $this->CI->sweepstake_model->send_promotional_email(3,$lead_id,$email_lead_data);
                            } else if($is_valid!=0) {
                                $lead_updt_data=array();
                                if($lead_info_id>0) $lead_updt_data['id']=$lead_info_id;
                                else $lead_updt_data['lead_id']=$lead_id;
                                $lead_updt_data['is_valid']=$is_valid;
                                $this->update_lead_info($lead_updt_data);
                            }

                            $lead_status=$valid;
                            if($is_valid==4) $lead_msg.='Record Rejected as Suspicious..!';
                            else if($is_valid==3) $lead_msg.='Record Rejected as Deleted..!';
                            else if($is_valid==2) $lead_msg.='Record Rejected as Blacklisted..!';
                            else { //Rohit=>Modified below to change response text, if for valid leads found duplicate then send only "Found Duplicate" else "Record Accepted for Processing"
                                if($duplicate_check) $lead_msg='Found Duplicate..!';
                                else $lead_msg='Record Accepted for Processing..!';
                            }
                            $api_log[$sr]['is_valid']=$is_valid;
                            $api_log[$sr]['plausibility_count']=$plausibility_count;
                        } else { //Record not saved in mongo, something went wrong
                            $lead_status=false;
                            $lead_msg='Unable to Registered, Please Try Again..!';
                            $reason='Unable to Insert Record..!';
                        }
                    }
                } else {
                    $lead_status=false;
                    $api_log[$sr]['validation_flag']=1;
                    $lead_msg='Field Validation fails';
                    $reason=isset($validation_result['reason']) ? (is_array($validation_result['reason']) ? implode(" and ",$validation_result['reason']) : $validation_result['reason']) : 'Reason->Validation Fails..!';
                }
            } else { // Mandatory Fields Check Fails
                $lead_status=false;
                $api_log[$sr]['mandatory_flag']=1;
                if($format_data['mandatory_fields']!='') {
                    $lead_msg='Mandatory Fields check fails';
                    $reason='Mandatory Fields check fails for => '.$format_data['mandatory_fields'].'..!';
                } else {
                    $lead_msg='Unable to Accept, Please Try Again..!';
                    $reason='Data Formatting Fails..!';
                }
            }
            $lead_result[$unique_id]=array('lead_status'=>$lead_status,'lead_msg'=>$lead_msg,'reason'=>$reason);
            $api_log[$sr]['reason']=$reason;
            $sr++;
            $simple_msg=($reason!='' ? $reason : $lead_msg);
            $reason_id=($lead_id!='' ? 'LeadId='.$lead_id : 'SessId='.$unique_id);
            if($final_reason!='') $final_reason.='<br>';
            $final_reason.='{'.$reason_id.'--(Email='.$log_email.')=>'.$simple_msg.'}';
        }
        $this->result=array('status'=>true,'msg'=>'Received Valid Response..!','lead_result'=>$lead_result);
        $this->process_log=$api_log;
        $this->final_reason=$final_reason;
        $this->simple_msg=$simple_msg;
    } //Rohit=> lead_data_fetch() Ends

    //Rohit=> Final Result of Process
    public function registration_result() {
        return array('process_result'=>$this->result,'process_log'=>$this->process_log,'final_reason'=>$this->final_reason,'simple_msg'=>$this->simple_msg);
    } //Rohit=> result() Ends

    //Rohit=> If found duplicate, then update mongo Lead record to maintain history array
    private function update_mongo_lead($user_data) {
        if(!empty($user_data) && $user_data['user_id']!='') {
            $single_data=array();
            foreach($user_data as $field=>$value) {
                if($value!='' || (is_array($value) && !empty($value))) {
                    if($field!='user_id' && !in_array($field,$this->single_fields)) {
                        $identifier=$this->CI->lead_model->upsert_identifier($field);
                        if(method_exists($this->CI->lead_validator,'upsert_'.$identifier.'_document')) {
                            $ss=$this->CI->lead_validator->{'upsert_'.$identifier.'_document'}($field,$value,$user_data['user_id']);
                        }
                    } else if(!is_array($value) && $value!='') {
                        $single_data[$field]=$value;
                    }
                }
            }
            if(!empty($single_data)) {
                $single_data['activation_key']=time();
                $this->mongo_db->where('_id',new MongoId($user_data['user_id']))->set($single_data)->update('leads',array('upsert'=>true,'multi'=>true));
            }
        }
        return true;
    } //Rohit=> update_mongo_lead() Ends

    // Update lead_info table record
    private function update_lead_info($data=array()) {
        if(!empty($data)) {
            $proceed=false;
            if(isset($data['id']) && $data['id']!='') {
                $proceed=true;
                $this->write_db->where('id',$data['id']);
            } else {
                if(isset($data['lead_id']) && $data['lead_id']!='') {
                    $proceed=true;
                    $this->write_db->where('lead_id',$data['lead_id']);
                }
                if(isset($data['email']) && $data['email']!='') {
                    $proceed=true;
                    $this->write_db->where('email',$data['email']);
                }
                $this->write_db->where('duplicate_flag !=',1);
            }
            if($proceed) {
                $this->write_db->update(LEAD_INFO,$data);
                $this->write_db->limit('1');
                return true;
            }
        }
        return false;
    } //Rohit=> update_lead_info() Ends

    //Rohit=> Formatting Fetched Lead Data & checking Mandatory Fields & checking auto corrections, etc..
    private function formatting_data($post_lead=array(),$auto_correction_rules=array()) {
        $result=$lead_data=$user_data=$history_data=$validate_fields_error=array();
        $mandatory_fields_str=$validate_fields_str='';
        $status=null;
        $lead_reg_date=$mongo_reg_date='';
        $curr_date=date('Y-m-d H:i:s');
        $mongo_date=new MongoDate();
        foreach($this->data_fields as $field) {
            $value=$user_value=(isset($post_lead[$field]) && trim($post_lead[$field])!='') ? trim($post_lead[$field]) : '';
            if($value!='') {
                //Rohit=> Auto Correcting the Field Values using the Defined Rules.
                if(array_key_exists($field,$auto_correction_rules)) {
                    $correction_result=$this->CI->lead_validator->auto_correct($value,$auto_correction_rules[$field]);
                    if(isset($correction_result['corrected']) && $correction_result['corrected']) {
                        $value=(isset($correction_result['corrected_value']) && $correction_result['corrected_value']!='') ? $correction_result['corrected_value'] : $value;
                    }
                }
                switch ($field) {
                    case 'email':
                        $user_value=$value=preg_replace('/\s+/','',strtolower($value));
                        $valid=preg_match("/([^\p{Z}]+@[\p{L}\p{M}\p{N}.-]+\.(\p{L}\p{M}*){2,6})/ui",$value);
                        if($valid) {
                            $lead_data['single_optin_flag']='1';
                            $lead_data['email_optin_flag']='1';
                        } else { //Rohit=> Email Format is Wrong or Invalid
                            $status=false;
                            if($mandatory_fields_str=='') $mandatory_fields_str="Wrong Format=>".$field.'('.$value.')';
                            else $mandatory_fields_str.=", Wrong Format=>".$field.'('.$value.')';
                            break;
                        }
                    break;
                    case 'area_code':
                    case 'phone':
                        $value=preg_replace('/\s+/','',$value);
                        $value=preg_replace('/[^0-9]+/','',$value);
                        $user_value=$value=ltrim($value,'0');
                        if($field=='area_code' && $this->area_code_check) {
                            if($this->allowed_area_code_digits!='') {
                                $area_code_length=strlen((string)$value);
                                if($area_code_length > $this->allowed_area_code_digits) {
                                    if($this->country_code_check && $this->country_code!='') {
                                        if(strpos($value,$this->country_code)===0) {
                                            $user_value=$value=substr($value,strlen($this->country_code));
                                        }
                                    }
                                    $area_code_limit='-'.(int)$this->allowed_area_code_digits;
                                    $user_value=$value=substr($value,$area_code_limit);
                                }
                            }
                        }
                        if($field=='phone') {
                            if($this->country_code_check && $this->country_code!='') {
                                if(strpos($value,$this->country_code)===0) {
                                    $user_value=$value=substr($value,strlen($this->country_code));
                                }
                            }
                            if($this->allowed_phone_digits!='') {
                                $phone_length=strlen((string)$value);
                                if($phone_length > $this->allowed_phone_digits) {
                                    $phone_limit='-'.(int)$this->allowed_phone_digits;
                                    $user_value=$value=substr($value,$phone_limit);
                                }
                            }
                        }
                    break;
                    case 'salutation':
                        $user_value=$gender_val=strtolower($value);
                        if(array_key_exists($gender_val,$this->gender_code)) {
                            $lead_data['gender']=$this->gender_code[$gender_val];
                        } else if(array_key_exists($gender_val,$this->gender_value) && isset($this->gender_value[$gender_val][$this->country])) {
                            $lead_data['gender']=$gender_val;
                            $user_value=$value=$this->gender_value[$gender_val][$this->country];
                        } else {
                            $lead_data['gender']='';
                            if(in_array($field,$this->mandatory_fields)) {
                                $status=false;
                                if($mandatory_fields_str=='') $mandatory_fields_str="Wrong Value=>".$field.'('.$value.')';
                                else $mandatory_fields_str.=", Wrong Value=>".$field.'('.$value.')';
                                break;
                            }
                        }
                        $value=ucfirst($value);
                    break;
                    case 'first_name':
                    case 'last_name':
                    case 'street':
                    case 'city':
                        $user_value=$value=strtolower($value);
                        $value=ucwords($value);
                    break;
                    case 'date_of_birth':
                        $dob_date=new DateTime($value);
                        $value=$dob_date->format('d-m-Y');
                        $user_value=$value=str_replace('/','-',$value);
                        $dob_ymd=filter_var($value,FILTER_VALIDATE_REGEXP,array("options"=>array("regexp"=>"/^(?:(19|20)[0-9]{2})[\-\/.](0[1-9]|1[012])[\-\/.](0[1-9]|[12][0-9]|3[01])$/")));
                        if(!$dob_ymd) {
                            $year=$month=$day='';
                            $dob_dmy=filter_var($value,FILTER_VALIDATE_REGEXP,array("options"=>array("regexp"=>"/^(?:(0[1-9]|[12][0-9]|3[01])[\-\/.](0[1-9]|1[012])[\-\/.](19|20)[0-9]{2})$/")));
                            if($dob_dmy) list($day,$month,$year)=explode("-",$value);
                            else { //Rohit=> DOB Format is not as per expectation
                                $status=false;
                                if($mandatory_fields_str=='') $mandatory_fields_str="Wrong Format=>".$field.'('.$value.')';
                                else $mandatory_fields_str.=", Wrong Format=>".$field.'('.$value.')';
                                break;
                            }
                            if($year!='' && $month!='' && $day!='') $user_value=$value=$year.'-'.$month.'-'.$day;
                            else $user_value=$value='';
                        }
                    break;
                    default :
                    break;
                }
                if($status===null) $status=true;
            } else if(in_array($field,$this->mandatory_fields)){
                $status=false;
                if($mandatory_fields_str=='') $mandatory_fields_str=$field;
                else $mandatory_fields_str.=", ".$field;
                break;
            }
            $lead_data[$field]=$value;
            if($status==true && in_array($field,$this->user_data_fields) && $user_value!='') {
                switch ($field) {
                    case 'email':
                    case 'phone':
                        $user_data[$field]=$this->mongo_field_array($field,$user_value,$mongo_date);
                        if($field=='email') {
                            $user_data['single_optin_flag']='1';
                            $user_data['email_optin_flag']='1';
                            $user_data['email_oi_timestamp']=$mongo_date;
                        } else if($field=='phone') {
                            $user_data['phone_optin_flag']='0';
                            $user_data['phone_oi_timestamp']=$mongo_date;
                        }
                    break;
                    case 'first_name':
                    case 'last_name':
                    case 'street':
                    case 'city':
                        $user_data[$field]=$this->mongo_field_array($field,$user_value,$mongo_date);
                    break;
                    case 'date_of_birth':
                        $user_data['date_of_birth']=$this->mongo_db->date(strtotime($user_value));
                        list($year,$month,$day)=explode("-",$user_value);
                        $user_data['date_of_birth_day']=$day;
                        $user_data['date_of_birth_month']=$month;
                        $user_data['date_of_birth_year']=$year;
                    break;
                    default :
                        if($field=='house_number' || $field=='post_code') {
                            $user_data[$field]=$this->mongo_field_array($field,$user_value,$mongo_date);
                            if($field=='post_code' && $user_data['post_code']!='') {
                                $user_data['post_code_optin_flag']='0';
                                $user_data['post_code_oi_timestamp']=$mongo_date;
                            }
                        } else {
                            $user_data[$field]=$user_value;
                        }
                    break;
                }
            }
        }

        if($status==true) {
        if(isset($lead_data['phone']) && $lead_data['phone']!='' && (!$this->area_code_check || (isset($lead_data['area_code']) && $lead_data['area_code']!=''))) {
            if($this->area_code_check) {
                $lead_data['phone_type']=$this->get_phone_type($this->country,$lead_data['phone'],$lead_data['area_code']);
                $lead_data['area_code']=ltrim($lead_data['area_code'],'0');
            } else {
                $lead_data['phone_type']=$this->get_phone_type($this->country,$lead_data['phone'],'');
            }
            if(isset($user_data['phone'][0]) && !empty($user_data['phone'][0])) {
                if($this->area_code_check) {
                    if($lead_data['phone_type']!='') $phone_fields_arr=array('type'=>$lead_data['phone_type'],'area_code'=>$lead_data['area_code']);
                    else $phone_fields_arr=array('area_code'=>$lead_data['area_code']);
                } else {
                    if($lead_data['phone_type']!='') $phone_fields_arr=array('type'=>$lead_data['phone_type']);
                    else $phone_fields_arr=array();
                }
                $user_data['phone'][0]=array_merge($user_data['phone'][0],$phone_fields_arr);
            }
        }

        $filter_type=isset($this->constants['filter_type']) ? $this->constants['filter_type'] : '';
        $registration_type=isset($this->constants['registration_type']) ? $this->constants['registration_type'] : '';
        $sweepstake_id=isset($this->constants['sweepstake_id']) ? $this->constants['sweepstake_id'] : '';
        $campaign_id=isset($this->constants['campaign_id']) ? $this->constants['campaign_id'] : '';
        $marketing_campaign_id=isset($this->constants['marketing_campaign_id']) ? $this->constants['marketing_campaign_id'] : '';
        $marketing_partner_id=isset($this->constants['marketing_partner_id']) ? $this->constants['marketing_partner_id'] : '';
        $sub_marketing_partner_id=isset($this->constants['sub_marketing_partner_id']) ? $this->constants['sub_marketing_partner_id'] : '';
        $publisher_id=isset($this->constants['publisher_id']) ? $this->constants['publisher_id'] : '';
        $sweepstake_url=isset($this->constants['sweepstake_url']) ? $this->constants['sweepstake_url'] : '';
        $domain=isset($this->constants['domain']) ? $this->constants['domain'] : '';

        $lead_reg_date=((isset($lead_data['registration_date']) && strtotime($lead_data['registration_date'])>0) ? date('Y-m-d H:i:s',strtotime($lead_data['registration_date'])) : '');
        $mongo_reg_date=($lead_reg_date!='' ? date('c',strtotime($lead_reg_date)) : '');
        $lead_data['lead_id']='';
        $lead_data['country']=ucfirst($this->country);
        $lead_data['duplicate_flag']='0';
        $lead_data['static_field_3']=$filter_type;
        $lead_data['registration_type']=$registration_type;
        $lead_data['registration_date']=($lead_reg_date!='' ? $lead_reg_date : $curr_date);
        $lead_data['is_valid']='0';
        $lead_data['date_added']=$curr_date;
        $lead_data['sweepstake_id']= (isset($lead_data['sweepstake_id']) && $lead_data['sweepstake_id']!='') ? $lead_data['sweepstake_id'] : $sweepstake_id;
        $lead_data['campaign_id']= (isset($lead_data['campaign_id']) && $lead_data['campaign_id']!='') ? $lead_data['campaign_id'] : $campaign_id;
        $lead_data['marketing_campaign_id']= (isset($lead_data['marketing_campaign_id']) && $lead_data['marketing_campaign_id']!='') ? $lead_data['marketing_campaign_id'] : $marketing_campaign_id;
        $lead_data['marketing_partner_id']= (isset($lead_data['marketing_partner_id']) && $lead_data['marketing_partner_id']!='') ? $lead_data['marketing_partner_id'] : $marketing_partner_id;
        $lead_data['sub_marketing_partner_id']= (isset($lead_data['sub_marketing_partner_id']) && $lead_data['sub_marketing_partner_id']!='') ? $lead_data['sub_marketing_partner_id'] : $sub_marketing_partner_id;
        $lead_data['publisher_id']= (isset($lead_data['publisher_id']) && $lead_data['publisher_id']!='') ? $lead_data['publisher_id'] : $publisher_id;

        $user_data['user_id']='';
        $user_data['country']=$this->mongo_field_array('country',$this->country,$mongo_date);
        $user_data['update_reason']='registered';
        $user_data['current_status']='active';
        $user_data['unsubscribed']='0';
        $user_data['suspicious_flag']='0';
        $user_data['last_action']='details updated';
        $user_data['last_modified_source']='system';
        $user_data['activation_key']=time();
        $user_data['last_inserted_date']=($mongo_reg_date!='' ? $mongo_reg_date : $mongo_date);
        $user_data['last_date_modified']=($mongo_reg_date!='' ? $mongo_reg_date : $mongo_date);
        $user_data['traffic_source']=$lead_data['static_field_3'];
        $user_data['single_oi_ip']=$lead_data['ip_address'];
        $user_data['last_user_ip']=$lead_data['ip_address'];
        $user_data['last_publisher_id']=$lead_data['publisher_id'];
        $user_data['last_campaign_id']=$lead_data['campaign_id'];
        $user_data['last_domain']=$domain;
        $user_data['session_id']=$this->session_id;
        $user_data['double_optin_flag']='';
        $user_data['double_oi_ip']='';
        $user_data['double_oi_timestamp']='';
        $user_data['last_subdomain']='';
        $user_data['last_additional_id']='';

        foreach($this->user_history_fields as $history_field) {
            $history_data[$history_field]=(isset($user_data[$history_field]) ? $user_data[$history_field] : (isset($lead_data[$history_field]) ? $lead_data[$history_field] : (isset(${$history_field})) ? ${$history_field} : ""));
        }
        $history_data['date_inserted']=($mongo_reg_date!='' ? $mongo_reg_date : $mongo_date);
        $history_data['date_modified']=($mongo_reg_date!='' ? $mongo_reg_date : $mongo_date);
        $history_data['current_page']='1';
        $history_data['update_source']='system';
        $history_data['user_ip']=$lead_data['ip_address'];
        $history_data['domain_name']=$domain;
        $history_data['plausibility_error_count']='0';
        $user_data['registration_history']=array($history_data);
        }

        $result['status']=$status;
        $result['mandatory_fields']=$mandatory_fields_str;
        $result['user_data']=$user_data;
        $result['lead_data']=$lead_data;
        return $result;
    } //Rohit=> formatting_data() Ends

    //Rohit=> Checking & Validating Lead Record Field's Values with the Defined Vaidation Rule
    private function validating_data($data=array(),$fields_validation_rule=array()) {
        $plausibility=array();
        if(!empty($data)) {
            $dob_check=true;
            $plausibility['user_id']=$data['user_id'];
            $plausibility['check']=true;
            $plausibility['plausibility_count']=0;
            $plausibility['reason']='';
            $plausibility['severity']=0;

            foreach($this->data_fields as $field) {
                if($field=='email') $parameter_name='email_id';
                else if($field=='phone') $parameter_name='number';
                else if($field=='post_code') $parameter_name='post_code';
                else $parameter_name='value';
                $field_value=isset($data[$field]) ? (is_array($data[$field]) ? $data[$field][0][$parameter_name] : $data[$field]) : "";
                if($field_value!='') {
                    if(array_key_exists($field,$fields_validation_rule)) {
                        $min_length=isset($fields_validation_rule[$field]['min_length']) ? $fields_validation_rule[$field]['min_length'] : "";
                        $max_length=isset($fields_validation_rule[$field]['max_length']) ? $fields_validation_rule[$field]['max_length'] : "";
                        if($field=='date_of_birth') {
                            $dob_check=false;
                            $min_age=$min_length!= '' ? $min_length : 18;
                            $max_age=$max_length!= '' ? $max_length : 69;
                            $dob=date('d-m-Y',$field_value->sec);
                            $from=new DateTime($dob);
                            $to=new DateTime('today');
                            $age=$from->diff($to)->y;
                            $valid=($age<$min_age || $age>$max_age) ? false : true;
                            if(!$valid) {
                                $plausibility['check']=false;
                                $plausibility['plausibility_count']++;
                                $plausibility['reason'][]='Incorrect Input for '.$field.'='.$dob.'=>Reason=Min-'.$min_age.':Max-'.$max_age;
                                $plausibility['severity']=2;
                            }
                        } else {
                            if($min_length!='' || $max_length!='') {
                                if($min_length!='') {
                                    $min_valid=(strlen($field_value)<$min_length) ? false : true;
                                }
                                if($max_length!='') {
                                    $max_valid=(strlen($field_value)>$max_length) ? false : true;
                                }
                                if(!$min_valid || !$max_valid) {
                                    $plausibility['check']=false;
                                    $plausibility['plausibility_count']++;
                                    $plausibility['reason'][]='Incorrect Length for '.$field.'='.$field_value.'=>Reason=Min-'.$min_length.':Max-'.$max_length;
                                    if($plausibility['severity']!=2) $plausibility['severity']=1;
                                }
                            }
                            $start_with=isset($fields_validation_rule[$field]['start_with']) ? $fields_validation_rule[$field]['start_with'] : "";
                            if($start_with!='') {
                                $valid=(strrpos($field_value,$start_with,-strlen($field_value))!==false) ? true : false;
                                if(!$valid) {
                                    $plausibility['check']=false;
                                    $plausibility['plausibility_count']++;
                                    $plausibility['reason'][]='Incorrect Input for '.$field.'='.$field_value.'=>Reason=Startwith-'.$start_with;
                                    if($plausibility['severity']!=2) $plausibility['severity']=1;
                                }
                            }
                        }
                    }
                    switch ($field) {
                        case 'salutation':
                        case 'first_name':
                        case 'last_name':
                        case 'street':
                        case 'city':
                            if($field=='street') $alphabets_valid=true; //Rohit=> To Allowed Numerical values in Street field
                            else $alphabets_valid=preg_match("/^[a-zA-Z\s\pL]+$/u",$field_value);
                            $tripple_chars_valid=preg_match("/([a-zA-Z\s\pL])\\1\\1/",$field_value);
                            if(!$alphabets_valid || $tripple_chars_valid) {
                                if(!$alphabets_valid) $tmp_reason='Only Alphabets';
                                else if($tripple_chars_valid) $tmp_reason='No consecutive Characters';
                                else $tmp_reason='Text';
                                $plausibility['check']=false;
                                $plausibility['plausibility_count']++;
                                $plausibility['reason'][]='Incorrect Input for '.$field.'='.$field_value.'=>Reason='.$tmp_reason;
                                if($plausibility['severity']!=2) $plausibility['severity']=1;
                            }
                        break;
                        case 'email':
                            $kickbox_result=$this->CI->kickbox->verify($field_value);
                            if(isset($kickbox_result['result']) && $kickbox_result['result']!='deliverable') {
                                $tmp_reason='KickboxFailure'.((isset($kickbox_result['reason']) && $kickbox_result['reason']!='') ? '->('.$kickbox_result['reason'].')' : '');
                                $plausibility['check']=false;
                                $plausibility['plausibility_count']++;
                                $plausibility['reason'][]='Incorrect Input for '.$field.'='.$field_value.'=>Reason='.$tmp_reason;
                                $plausibility['severity']=2;
                            }
                        break;
                        case 'phone':
                            if($this->area_code_check) {
                                $area_code=@$data['phone'][0]['area_code'];
                                if($area_code!='' && in_array($area_code,$this->area_code_not_allowed)){
                                    $plausibility['check']=false;
                                    $plausibility['plausibility_count']++;
                                    $plausibility['reason'][]='Incorrect Input for '.$field.'='.$field_value.'=>Reason=NotAllowed';
                                    if($plausibility['severity']!=2) $plausibility['severity']=1;
                                }
                                $phone_number=$area_code.$field_value;
                            } else $phone_number=$field_value;
                            //$valid=preg_match('/^\d$/',$phone_number);
                            $digit_valid=ctype_digit($phone_number);
                            $tripple_digit_valid=preg_match("/([0-9])\\1\\1/",$field_value);
                            if(!$digit_valid || $tripple_digit_valid) {
                                if(!$digit_valid) $tmp_reason='Only Digits';
                                else if($tripple_digit_valid) $tmp_reason='No Consecutive Digits';
                                else $tmp_reason='Digits';
                                $plausibility['check']=false;
                                $plausibility['plausibility_count']++;
                                $plausibility['reason'][]='Incorrect Input for '.$field.'='.$phone_number.'=>Reason='.$tmp_reason;
                                if($plausibility['severity']!=2) $plausibility['severity']=1;
                            }
                        break;
                        case 'post_code':
                            if($this->country!='uk') { //Rohit=> Bypass for UK, as it has alphabets in post_code field
                                $valid=ctype_digit($field_value);
                                if(!$valid) {
                                    $plausibility['check']=false;
                                    $plausibility['plausibility_count']++;
                                    $plausibility['reason'][]='Incorrect Input for '.$field.'='.$field_value.'=>Reason=Digits';
                                    if($plausibility['severity']!=2) $plausibility['severity']=1;
                                }
                            }
                        break;
                        case 'date_of_birth':
                            if($dob_check) {
                                $dob=date('d-m-Y',$field_value->sec);
                                $from=new DateTime($dob);
                                $to=new DateTime('today');
                                $age=$from->diff($to)->y;
                                $valid=($age<18 || $age>69) ? false : true;
                                if(!$valid) {
                                    $plausibility['check']=false;
                                    $plausibility['plausibility_count']++;
                                    $plausibility['reason'][]='Incorrect Input for '.$field.'='.$dob.'=>Reason=Min-18:Max-69';
                                    $plausibility['severity']=2;
                                }
                            }
                        break;
                        default :
                        break;
                    }
                }//Rohit=>$field_value check ends
            }
        }
        return $plausibility;
    } //Rohit=> validating_data() Ends

    //Rohit=> Update mongo record as Suspicious
    private function mark_suspicious($user_data,$status_data,$flag=0) {
        if(!empty($user_data) && !empty($status_data) && isset($user_data['user_id']) && $user_data['user_id']!='') {
            $updt_user=array();
            $mongo_date=new MongoDate();
            $plausibility_count=isset($status_data['plausibility_count']) ? $status_data['plausibility_count'] : '';
            $reason=isset($status_data['reason']) ? implode(",",$status_data['reason']) : 'suspicious undefined';
            $updt_user['last_date_modified']=$mongo_date;
            $updt_user['last_modified_source']='system';
            $updt_user['last_action']='marked suspicious';
            $updt_user['current_status']='suspicious';
            $updt_user['suspicious_flag']=1;
            $suspicious['title']='Badword check failed';
            $suspicious['reason']=$reason;
            if($flag==1) {
                $suspicious['result']=$status_data['result'];
            }
            $suspicious['date_inserted']=$mongo_date;
            $updt_user['registration_history']=$user_data['registration_history'];
            $updt_user['registration_history'][0]['current_status']='suspicious';
            $updt_user['registration_history'][0]['suspicious_flag']=1;
            if($plausibility_count!='') $updt_user['registration_history'][0]['plausibility_error_count']=$plausibility_count;
            $updt_user['registration_history'][0]['title']='Badword check failed';
            $updt_user['registration_history'][0]['reason']=$reason;
            $updt_user['suspicious']=$suspicious;
            $this->mongo_db->where('_id',new MongoId($user_data['user_id']))->set($updt_user)->update('leads',array('upsert'=>true,'multi'=>true));
            return $reason;
        } else return false;
    } //Rohit=> mark_suspicious() Ends

    //Rohit=> Update deleted or unsubscribed mongo record as active
    private function activate_lead($user_data=array(),$status='') {
        if(!empty($user_data) && $status!='' && isset($user_data['user_id']) && $user_data['user_id']!='') {
            $updt_user=array();
            $mongo_date=new MongoDate();
            $last_action=$current_status='active';

            $updt_user['last_date_modified']=$mongo_date;
            $updt_user['last_modified_source']='system';
            $updt_user['last_action']=$last_action;
            $updt_user['current_status']=$current_status;
            $updt_user['last_user_ip']=$user_data['last_user_ip'];
            $updt_user['last_domain']=$user_data['last_domain'];
            $updt_user['last_subdomain']=$user_data['last_subdomain'];
            $updt_user['last_publisher_id']=$user_data['last_publisher_id'];
            $updt_user['last_campaign_id']=$user_data['last_campaign_id'];
            $updt_user['last_additional_id']=$user_data['last_additional_id'];
            if($status=='deleted') {
                $delete['title']='';
                $delete['reason']='';
                $delete['flag']=0;
                $delete['date_inserted']='';
                $updt_user['deleted']=$delete;
                $reason='Marked Deleted Lead as Active';
            } else if($status=='unsubscribed') {
                $reason='Marked Unsubscribed Lead as Active';
            }
            $history['registration_history']=$user_data['registration_history'];
            $history['registration_history'][0]['current_status']=$current_status;
            $history['registration_history'][0]['email_optin_flag']=$user_data['registration_history'][0]['email_optin_flag'];
            $history['registration_history'][0]['phone_optin_flag']=$user_data['registration_history'][0]['phone_optin_flag'];
            $history['registration_history'][0]['post_code_optin_flag']=$user_data['registration_history'][0]['post_code_optin_flag'];
            $history['registration_history'][0]['single_optin_flag']=$user_data['registration_history'][0]['single_optin_flag'];
            $history['registration_history'][0]['double_optin_flag']=$user_data['registration_history'][0]['double_optin_flag'];
            $history['registration_history'][0]['post_code_oi_timestamp']=$mongo_date;
            $history['registration_history'][0]['phone_oi_timestamp']=$mongo_date;
            $history['registration_history'][0]['email_oi_timestamp']=$mongo_date;
            $history['registration_history'][0]['single_oi_ip']=$user_data['single_oi_ip'];
            $history['registration_history'][0]['double_oi_ip']='';
            $history['session_id']=$user_data['session_id'];
            $updt_user['unsubscribed']=0;
            $updt_user['suspicious_flag']=0;

            $this->mongo_db->where('_id', new MongoId($user_data['user_id']))->set($updt_user)->update('leads',array('upsert'=>true,'multi'=>true));
            $this->mongo_db->where('_id', new MongoId($user_data['user_id']))->addtoset('registration_history',$history['registration_history'])->update('leads');
            return $reason;
        } else return false;
    } //Rohit=> activate_lead() Ends

    //Rohit=> Update mongo record as deleted
    private function delete_invalid_lead($user_data=array(),$status_data=array(),$status='') {
        if(!empty($user_data) && !empty($status_data) && isset($user_data['user_id']) && $user_data['user_id']!='') {
            $updt_user=array();
            $mongo_date=new MongoDate();
            $plausibility_count='';
            if($status=='blacklist') {
                $last_action='blacklist';
                $current_status='blacklisted';
                $title='Blacklist delete';
            } else if($status=='badword') {
                $last_action='delete';
                $current_status='deleted';
                $title='Badword delete';
            } else if($status=='plausibility_fail') {
                $plausibility_count=isset($status_data['plausibility_count']) ? $status_data['plausibility_count'] : 0;
                $last_action='delete';
                $current_status='deleted';
                $title='Plausibility failed';
            } else {
                $last_action='delete';
                $current_status='deleted';
                $title='Invalid';
            }
            $reason=isset($status_data['reason']) ? (is_array($status_data['reason']) ? implode(" and ",$status_data['reason']) : $status_data['reason']) : 'Reason->'.$title;

            $updt_user['last_date_modified']=$mongo_date;
            $updt_user['last_modified_source']='system';
            $updt_user['last_action']=$last_action;
            $updt_user['current_status']=$current_status;
            $delete['title']=$title;
            $delete['reason']=$reason;
            $delete['flag']=1;
            $delete['date_inserted']=$mongo_date;
            $history['registration_history']=$user_data['registration_history'];
            if($plausibility_count!='') $history['registration_history'][0]['plausibility_error_count']=$plausibility_count;
            $history['registration_history'][0]['current_status']=$current_status;
            $history['registration_history'][0]['title']=$title;
            $history['registration_history'][0]['reason']=$reason;
            $updt_user['last_user_ip']=$user_data['last_user_ip'];
            $updt_user['last_domain']=$user_data['last_domain'];
            $updt_user['last_subdomain']=$user_data['last_subdomain'];
            $updt_user['last_publisher_id']=$user_data['last_publisher_id'];
            $updt_user['last_campaign_id']=$user_data['last_campaign_id'];
            $updt_user['last_additional_id']=$user_data['last_additional_id'];
            $updt_user[$current_status]=$delete;
            $updt_user['email_optin_flag']=$history['registration_history'][0]['email_optin_flag']="0";
            $updt_user['phone_optin_flag']=$history['registration_history'][0]['phone_optin_flag']="0";
            $updt_user['post_code_optin_flag']=$history['registration_history'][0]['post_code_optin_flag']="0";
            //$updt_user['single_optin_flag']=$history['registration_history'][0]['single_optin_flag']="0";
            $updt_user['double_optin_flag']=$history['registration_history'][0]['double_optin_flag']="0";
            $updt_user['post_code_oi_timestamp']=$history['registration_history'][0]['post_code_oi_timestamp']=$mongo_date;
            $updt_user['phone_oi_timestamp']=$history['registration_history'][0]['phone_oi_timestamp']=$mongo_date;
            $updt_user['email_oi_timestamp']=$history['registration_history'][0]['email_oi_timestamp']=$mongo_date;
            $updt_user['single_oi_ip']=$history['registration_history'][0]['single_oi_ip']=$user_data['single_oi_ip'];
            $updt_user['double_oi_ip']=$history['registration_history'][0]['double_oi_ip']='';
            $this->mongo_db->where('_id', new MongoId($user_data['user_id']))->set($updt_user)->update('leads',array('upsert'=>true,'multi'=>true));
            $this->mongo_db->where('_id', new MongoId($user_data['user_id']))->addtoset('registration_history',$history['registration_history'])->update('leads');
            return $reason;
        } else return false;
    } //Rohit=> delete_invalid_lead() Ends

    //Rohit=> Deriving the phone type according to the country
    private function get_phone_type($country='',$phone='',$area_code='') {
        $phone_type='mobile';
        if(!empty($this->landline_access_codes) || !empty($this->mobile_access_codes)) {
            if($country=='australia') {
                if(in_array(substr($phone,0,1),$this->landline_access_codes)) {
                    $phone_type='landline';
                } /* else if(in_array(substr($phone,0,1),$this->mobile_access_codes)) {
                    $phone_type='mobile';
                } */ //Rohit=> No need to check as default defined is mobile only
            } else if($country=='germany') {

            } else if($country=='uk') {
                foreach($this->landline_access_codes as $landline_access_codes) {
                    if(strrpos($phone,$landline_access_codes,-strlen($phone))!==false) {
                        $phone_type='landline';
                    }
                }
                /* if(in_array(substr($phone,0,1),$this->mobile_access_codes)) {
                    $phone_type='mobile';
                } */ //Rohit=> No need to check as default defined is mobile only
            }
        }
        return $phone_type;
    } //Rohit=> get_phone_type() Ends

    //Rohit=> Assigning Optins for fetched Lead data for mongo
    private function mongo_field_array($field='',$value='',$mongo_date='') {
        $return=array();
        if($field=='email' || $field=='phone' || $field=='post_code') {
            if($field=='email') $field_name='email_id';
            else if($field=='phone') $field_name='number';
            else $field_name=$field;
            $field_optin=$field.'_optin_flag';
            $return=array($field_name=>$value,
                'update_source'=>'system',
                'date_inserted'=>$mongo_date,
                'date_modified'=>$mongo_date,
                'sweepstake'=>array('session_id'=>$this->session_id),
                $field_optin=>'0',
                'single_optin_flag'=>'1',
                'double_optin_flag'=>'0',
                'current_status'=>'active',
                'suspicious_flag'=>'0',
            );
        } else {
            $return=array('value'=>$value,
                'update_source'=>'system',
                'date_inserted'=>$mongo_date,
                'date_modified'=>$mongo_date,
                'sweepstake'=>array('session_id'=>$this->session_id),
            );
        }
        return array($return);
    } //Rohit=> mongo_field_array() Ends

    //Rohit=> Checking Lead already exists in Mongo Leads collection (for duplication)
    private function user_exists($user_data=array()) {
        $exists_flag=false;
        $user_id=$total_email_ids=$unsubscribed=$current_status=$session_id='';
        if(isset($user_data['email'][0]['email_id']) && $user_data['email'][0]['email_id']!='') {
            $match_arr['email_id']=$user_data['email'][0]['email_id'];
            $where['email']['$all'][]['$elemMatch']=$match_arr;
            $lead_existing=$this->mongo_db->select(array('_id','email','unsubscribed','current_status','session_id'))->where($where)->order_by("last_inserted_date","desc")->limit(1)->get('leads');
            if(!empty($lead_existing) && isset($lead_existing[0]['_id']) && $lead_existing[0]['_id']!='') {
                $user_id=(string)$lead_existing[0]['_id'];
                $total_email_ids=isset($lead_existing[0]['email']) ? count($lead_existing[0]['email']) : 0;
                $unsubscribed=isset($lead_existing[0]['unsubscribed']) ? $lead_existing[0]['unsubscribed'] : 0;
                $current_status=isset($lead_existing[0]['current_status']) ? $lead_existing[0]['current_status'] : '';
                $session_id=isset($lead_existing[0]['session_id']) ? (string)$lead_existing[0]['session_id'] : '';
                $exists_flag=true;
            } else {
                $first_name = (isset($user_data['first_name'][0]['value']) && $user_data['first_name'][0]['value'] != '') ? $user_data['first_name'][0]['value'] : '';
                $last_name = (isset($user_data['last_name'][0]['value']) && $user_data['last_name'][0]['value'] != '') ? $user_data['last_name'][0]['value'] : '';
                $date_of_birth = (isset($user_data['date_of_birth']) && $user_data['date_of_birth'] != '') ? $user_data['date_of_birth'] : '';
                if($first_name!='' && $last_name!='' && $date_of_birth!='') {
                    $where['first_name']['$all'][]['$elemMatch']['value'] = $first_name;
                    $where['last_name']['$all'][]['$elemMatch']['value'] = $last_name;
                    $where['date_of_birth'] = $date_of_birth;
                    $arr['$and'] = array($where);
                    $lead_existing=$this->mongo_db->select(array('_id','email','unsubscribed','current_status','session_id'))->where($arr)->order_by("last_inserted_date", "desc")->limit(1)->get('leads');
                    if(!empty($lead_existing) && isset($lead_existing[0]['_id']) && $lead_existing[0]['_id']!='') {
                        $user_id=(string)$lead_existing[0]['_id'];
                        $total_email_ids=isset($lead_existing[0]['email']) ? count($lead_existing[0]['email']) : 0;
                        $unsubscribed=isset($lead_existing[0]['unsubscribed']) ? $lead_existing[0]['unsubscribed'] : 0;
                        $current_status=isset($lead_existing[0]['current_status']) ? $lead_existing[0]['current_status'] : '';
                        $session_id=isset($lead_existing[0]['session_id']) ? (string)$lead_existing[0]['session_id'] : '';
                        $exists_flag=true;
                    }
                }
            }
        }
        return array('status'=>$exists_flag,'user_id'=>$user_id,'total_email_ids'=>$total_email_ids,'unsubscribed'=>$unsubscribed,'current_status'=>$current_status,'session_id'=>$session_id);
    } //Rohit=> user_exists() Ends

    //Rohit=> Sending DOI mail to fetched lead
    public function send_doi_mail($sweepstake_id='',$email_data=array()) {
        $status=false;
        if($sweepstake_id>0 && !empty($email_data) && isset($email_data['lead_info_id']) && $email_data['lead_info_id']>0 && isset($email_data['email']) && $email_data['email']!='') {
            $lead_info_id=$email_data['lead_info_id'];
            $email=$email_data['email'];
            $salutation=isset($email_data['salutation']) ? $email_data['salutation'] : "";
            $first_name=isset($email_data['first_name']) ? $email_data['first_name'] : "";
            $last_name=isset($email_data['last_name']) ? $email_data['last_name'] : "";

            $sweepstake_details=$this->CI->sweepstake_model->get_sweepstake_admin_configuration_details($sweepstake_id);
            $doi_configuration_email_template_id=isset($sweepstake_details[0]['doi_configuration_email_template_id']) ? $sweepstake_details[0]['doi_configuration_email_template_id'] : 0;
            if($doi_configuration_email_template_id>0) {
                $doi_details=$this->CI->global_management_model->get_doi_configuration_details($doi_configuration_email_template_id);
                if(!empty($doi_details)) {
                    $mail_data=array();
                    $subject=$doi_details[0]['subject'];
                    $from_email=$doi_details[0]['sender_email'];
                    $mail_data['message']=$doi_details[0]['email_body'];
                    $mail_data['sender_name']=$doi_details[0]['sender_name'];
                    $mail_data['salutation']=$salutation;
                    $mail_data['first_name']=$first_name;
                    $mail_data['last_name']=$last_name;

                    $sweepstake_url=base_url();
                    $user_id=$email_data['user_id'];
                    $activation_code=$email_data['activation_key'];
                    $total_email_ids=$email_data['total_email_ids'];
                    $converted_last_modified_date=$email_data['last_date_modified']->sec; // to know for which email DOI has been updated
                    $imprint_url=$sweepstake_url."sweepstakes/publish_sweepstake/publish/".$sweepstake_id."-".$this->country."/"."imprint_".$sweepstake_id."_1.php";
                    $activation_link=$sweepstake_url."sweepstakes/publish_sweepstake/register_user/".$sweepstake_id."/doi_page/".$user_id."/".$activation_code."/".$total_email_ids."/".$converted_last_modified_date."/".$this->country;
                    $unsubscribe_link=$sweepstake_url."sweepstakes/publish_sweepstake/unsubscribe/".$sweepstake_id."/".$user_id."/".$activation_code."/".$total_email_ids."/".$this->country;
                    $mail_data['imprint_url']=$imprint_url;
                    $mail_data['activation_link']=$activation_link;
                    $mail_data['unsubscribe_link']=$unsubscribe_link;

                    $response=$this->CI->custom_email->send_mail($from_email,$email,"","",$subject,$mail_data);
                    //Rohit=> 1513->Storing SES Response in table
                    ses_mail_tracker($this->country,'doi_email',$email,$lead_info_id,$response);
                    $status=true;
                }
            }
        }
        return $status;
    }



} //Rohit=> Class Lead_registration_process Ends
/* End of file Lead_generation_api.php */
