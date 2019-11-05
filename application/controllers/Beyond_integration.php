<?php if(!defined('BASEPATH')) exit('No direct script access allowed');
/**
 * CodeIgniter Controller for Integration of Beyond API
 *
 * @package	CodeIgniter
 * @category	Controller
 * @author	Rohit Patil (rohitpatil30) @ Codaemon Softwares, Pune
 */

class Beyond_integration extends CI_Controller {

    function __construct() {
        parent::__construct();
        $this->load->model("beyond/beyond_model");
    }

    //Rohit=> When user is added (after doi &/or mmpg) add it to beyond portal
    public function add_user_to_beyond($lead_id='',$country='',$update_flag=true) { //Rohit=>RED-1712 Always update to Beyond (means to check if lead exists)
        if($country!='' && $lead_id!='') {
            $status=$msg='';
            $activity_type='Beyond_Registration';
            $class_name='Beyond_Integration';
            $action_called='add_user_to_beyond';
            $lead_data=$this->beyond_model->get_beyond_ready_user_details($lead_id,$country);
            if(!empty($lead_data)) {
                $this->load->library("Beyond_api",array('country'=>$country));
                $soi_unsubscribe_status=0;
                $beyond_user_id_arr=array();
                $soi_unsubscribed_flag=$proceed_flag=$duplicate_flag=false;
                $soi_doi_flag='soi'; //Rohit=> Initially taken as SOI
                if($lead_data['double_optin_flag']=='1')  $soi_doi_flag='doi'; //Rohit=>RED-1637 if DOI is done then taken for DOI

                //Rohit=>Checking if SOI unsubscribed is allowed for Country
                $this->config->load('beyond');
                $beyond_config=$this->config->item('allowed_country_beyond_soi_unsubscribed');
                $soi_unsubscribed_allowed=(isset($beyond_config[$country]) ? $beyond_config[$country] : false);

                //Rohit=>RED-1637 Take Previous Beyond-User-Ids from DB to Concat New Ids to it
                $lead_beyond_user_str=$lead_data['beyond_user_id'];
                if($lead_beyond_user_str!='') {
                    $lead_prev_beyond_user_id=json_decode($lead_beyond_user_str,true);
                    if(!empty($lead_prev_beyond_user_id)) {
                        if(is_array($lead_prev_beyond_user_id)) $beyond_user_id_arr=$lead_prev_beyond_user_id;
                        else $beyond_user_id_arr['earlier-'.$lead_data['updated_to_beyond']]=$lead_prev_beyond_user_id;
                    }
                }

                if($lead_data['duplicate_flag']=='2' && in_array($lead_data['updated_to_beyond'],array('1','3'))) { //Rohit=> lead found duplicate
                    $duplicate_flag=true;
                    if($soi_doi_flag=='doi') { //Rohit=> For DOI Duplicate
                        if($lead_data['updated_to_beyond']=='1') { //Rohit=> means previously added to DOI (& so already unsubscribed from SOI), so now Update to DOI without SOI unsubscribed
                            $proceed_flag=true;
                            $soi_unsubscribed_flag=false;
                        } else { //Rohit=> means previously added to SOI, so now update to DOI with SOI Unsubscribed
                            $proceed_flag=true;
                            $soi_unsubscribed_flag=true;
                        }
                    } else { //Rohit=> For SOI Duplicate
                        if($lead_data['updated_to_beyond']=='1') { //Rohit=> means previously added to DOI (& so already unsubscribed from SOI), so now nothing to do
                            $proceed_flag=false;
                            $soi_unsubscribed_flag=false;
                        } else { //Rohit=> previously added to SOI, so now update to SOI
                            $proceed_flag=true;
                            $soi_unsubscribed_flag=false;
                        }
                    }
                } else { //Rohit=>RED-1637 Lead Duplication failed
                    $duplicate_flag=false;
                    $proceed_flag=true;
                    $soi_unsubscribed_flag=true;
                }
                if($proceed_flag) {
                    if($soi_unsubscribed_allowed && $soi_doi_flag=='doi' && $soi_unsubscribed_flag) { //Rohit=> First Unsubscribed from SOI list
                        $user_details=array('dateunjoin'=>date('Y-m-d H:i:s'));
                        if($this->beyond_api->update_beyond_user($user_details,$lead_data['email'],'soi')) $soi_unsubscribe_status=1;
                        else $soi_unsubscribe_status=2;
                    }
                    $user_details=array(
                        "email"=>$lead_data['email'],
                        "firstname"=>$lead_data['first_name'],
                        "lastname"=>$lead_data['last_name'],
                        "geburtsdatum"=>$lead_data['date_of_birth'],
                        "anrede"=>$lead_data['salutation'],
                    );

                    //Rohit=>RED-1672 For Lead Registration API's data, Send Partner's Name to Beyond Free-field
                    $lead_filter_type=isset($lead_data['static_field_3']) ? $lead_data['static_field_3'] : '';
                    if($lead_filter_type!='') {
                        $this->config->load('registration_api');
                        $reg_api_config=$this->config->item('allowed_country_api_lead_generation');
                        $filter_type=isset($reg_api_config[$country]['constants']['filter_type']) ? $reg_api_config[$country]['constants']['filter_type'] : '';
                        if($filter_type!='' && $lead_filter_type==$filter_type && isset($lead_data['marketing_partner_id']) && $lead_data['marketing_partner_id']>0) {
                            $marketing_partner_data=$this->beyond_model->get_marketing_partner_details($lead_data['marketing_partner_id']);
                            if(!empty($marketing_partner_data)) {
                                $user_details['freifeld']=isset($marketing_partner_data['client_name']) ? $marketing_partner_data['client_name'] : '';
                            }
                        }
                    }

                    if($duplicate_flag || $update_flag) { //Rohit=>RED-1637 Calling the Update Function of Beyond
                        $beyond_result=$this->beyond_api->update_beyond_user($user_details,'',$soi_doi_flag,'beyond_data_update');
                    } else { //Rohit=>RED-1637 Calling the Add Function of Beyond
                        $beyond_result=$this->beyond_api->add_beyond_user($user_details,$soi_doi_flag);
                    }
                    if(isset($beyond_result['status']) && $beyond_result['status'] && isset($beyond_result['result'])) {
                        $beyond_result=$beyond_result['result'];
                        if(!empty($beyond_user_id_arr)) $beyond_user_id_arr[$soi_doi_flag]=$beyond_result[$soi_doi_flag];
                        else $beyond_user_id_arr=$beyond_result;
                        $beyond_result_str=json_encode($beyond_user_id_arr);
                        $data=array();
                        $data['updated_to_beyond']=($soi_doi_flag=='doi') ? 1 : 3;
                        $data['beyond_user_id']=$beyond_result_str;
                        // Update user updates on beyond in Lead info
                        $this->beyond_model->update_lead_status($lead_data['email'],$data,$country);
                        $msg='Beyond Registration Success: LeadInfo=>{Email:'.$lead_data['email'].',Beyond_Result:'.$beyond_result_str.',Data_source_type:'.$soi_doi_flag.'}';
                        $status='Success';
                    } else {
                        $beyond_result_str=json_encode($beyond_result);
                        $msg='Beyond Registration Fails: LeadInfo=>{Email:'.$lead_data['email'].',Beyond_Result:'.$beyond_result_str.',Data_source_type:'.$soi_doi_flag.'}';
                        $status='Failed';
                    }
                    if($soi_unsubscribe_status!=0) {
                        if($soi_unsubscribe_status==1) $msg.='(SOI Unsubscribed Success)';
                        else $msg.='(SOI Unsubscribed Fails)';
                    }
                } else {
                    $other='=>(Proceed:'.$proceed_flag.',Duplicate:'.$duplicate_flag.',SOI-Unsubscribed:'.$soi_unsubscribed_flag.')';
                    $msg='Beyond Registration Not Needed: LeadInfo=>{Email:'.$lead_data['email'].',Data_source_type:'.$soi_doi_flag.'}'.$other;
                    $status='Failed';
                }
                $desc=array();
                $desc['activity_result']=$status;
                $desc['msg']=$msg;
                $this->save_loger($desc,$activity_type,$class_name,$action_called,$country);
            }
        }
    }

    //Rohit=> When user is Unsubscribed, send info to beyond portal
    public function unsubscribed_user_to_beyond($lead_id='',$country='',$unsubscribe_from='') {
        if($country!='' && $lead_id!='') {
            $status=$msg='';
            $activity_type='Beyond_Unsubscribed';
            $class_name='Beyond_Integration';
            $action_called='unsubscribed_user_to_beyond';
            $lead_data=$this->beyond_model->get_user_details($lead_id,$country);
            if(!empty($lead_data)) {
                if($unsubscribe_from=='') {
                    //Rohit=>RED-1637 Checking if to Unsubscribed lead from DOI or SOI Data Source
                    if($lead_data['double_optin_flag']=='1' || $lead_data['updated_to_beyond']=='1') $soi_doi_flag='doi';
                    else $soi_doi_flag='soi';
                } else $soi_doi_flag=$unsubscribe_from;
                if($soi_doi_flag!='') {
                    $user_details=array('member_id'=>'','dateunjoin'=>date('Y-m-d H:i:s'));
                    $this->load->library("Beyond_api",array('country'=>$country));
                    $beyond_result=$this->beyond_api->update_beyond_user($user_details,$lead_data['email'],$soi_doi_flag);
                    $beyond_result_str=json_encode($beyond_result);
                    if(isset($beyond_result['status']) && $beyond_result['status']) {
                        $this->beyond_model->update_lead_beyond_sent_status($lead_id,$country,'2');
                        $msg='Beyond Unsubscribed Success: LeadInfo=>{Email:'.$lead_data['email'].',Beyond_Result:'.$beyond_result_str.',Data_source_type:'.$soi_doi_flag.'}';
                        $status='Success';
                    } else {
                        $msg='Beyond Unsubscribed Failed: LeadInfo=>{Email:'.$lead_data['email'].',Beyond_Result:'.$beyond_result_str.',Data_source_type:'.$soi_doi_flag.'}';
                        $status='Failed';
                    }
                } else {
                    $msg='Beyond Unsubscribed Data-Source-Type not Found: LeadInfo=>{Email:'.$lead_data['email'].',Data_source_type:'.$soi_doi_flag.'}';
                    $status='Failed';
                }
            } else {
                $msg='Beyond Unsubscribed Lead not Found: LeadInfo=>{LeadId:'.$lead_id.',Data_source_type:'.$unsubscribe_from.'}';
                $status='Failed';
            }
            $desc=array();
            $desc['activity_result']=$status;
            $desc['msg']=$msg;
            $this->save_loger($desc,$activity_type,$class_name,$action_called,$country);
        }
    }

   //Rohit=> When user is Blacklisted, send info to beyond portal
    public function blacklist_user_to_beyond($lead_id='',$country='',$blacklist_from='') {
        if($country!='' && $lead_id!='') {
            $status=$msg='';
            $activity_type='Beyond_Blacklist';
            $class_name='Beyond_Integration';
            $action_called='blacklist_user_to_beyond';
            $lead_data=$this->beyond_model->get_user_details($lead_id,$country);
            if(!empty($lead_data)) {
                if($blacklist_from=='') {
                    //Rohit=>RED-1637 Checking if to BlackListed lead is from DOI or SOI Data Source
                    if($lead_data['double_optin_flag']=='1' || $lead_data['updated_to_beyond']=='1') $soi_doi_flag='doi';
                    else $soi_doi_flag='soi';
                } else $soi_doi_flag=$blacklist_from;
                if($soi_doi_flag!='') {
                    $user_details=array('email'=>$lead_data['email'],'reason'=>'Blacklisted from Back-Office');
                    $this->load->library("Beyond_api",array('country'=>$country));
                    $beyond_result=$this->beyond_api->blacklist_beyond_user($user_details,$lead_data['email'],$soi_doi_flag);
                    $beyond_result_str=json_encode($beyond_result);
                    if(isset($beyond_result['status']) && $beyond_result['status']) {
                        $this->beyond_model->update_lead_beyond_sent_status($lead_id,$country,'4');
                        $msg='Beyond Blacklist Success: LeadInfo=>{Email:'.$lead_data['email'].',Beyond_Result:'.$beyond_result_str.',Data_source_type:'.$soi_doi_flag.'}';
                        $status='Success';
                    } else {
                        $msg='Beyond Blacklist Failed: LeadInfo=>{Email:'.$lead_data['email'].',Beyond_Result:'.$beyond_result_str.',Data_source_type:'.$soi_doi_flag.'}';
                        $status='Failed';
                    }
                } else {
                    $msg='Beyond Blacklist Data-Source-Type not Found: LeadInfo=>{Email:'.$lead_data['email'].',Data_source_type:'.$soi_doi_flag.'}';
                    $status='Failed';
                }
            } else {
                $msg='Beyond Blacklist Lead not Found: LeadInfo=>{LeadId:'.$lead_id.',Data_source_type:'.$blacklist_from.'}';
                $status='Failed';
            }
            $desc=array();
            $desc['activity_result']=$status;
            $desc['msg']=$msg;
            $this->save_loger($desc,$activity_type,$class_name,$action_called,$country);
        }
    }

    //Rohit=>Beyond Web-hook calls this function when any lead unsubscribes in Beyond & used only for WHM-GERMANY
    //Rohit=>RED-1750 But now onwards, this will check on leads exists in all countries DB & if found, then it will be unsubscribed
    //Rohit=> Bcoz Beyond don't sends any Data-Source-Id with Web-hook to track & WHM(em6) Server too haves WHM-UK from where unsubscribed lead calls this function
    public function unsubscribed_from_beyond($country='') {
        $email=(isset($_GET['email']) && $_GET['email']!='' ? trim($_GET['email']) : "");
        //$source=(isset($_GET['source']) && $_GET['source']!='' ? trim($_GET['source']) : "");
        $request_ip=(isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR']!='' ? trim($_SERVER['REMOTE_ADDR']) : "");
        $this->config->load('beyond'); //Rohit=>RED-1637 Fetching WhiteList Beyond IP's from beyond config
        $beyond_config=$this->config->item('beyond_whitelist_ips');
        $beyond_unsubscribed_countries=array_keys($beyond_config);
        if($country!='' && in_array($country,$beyond_unsubscribed_countries)) $beyond_unsubscribed_countries=array($country);

        $status=$msg='';
        $response_flag=true;

        foreach($beyond_unsubscribed_countries as $country) {
            $beyonds_ip=(isset($beyond_config[$country]) && !empty($beyond_config[$country])) ? $beyond_config[$country] : array();
            if($email!='' && in_array($request_ip,$beyonds_ip)) {
                //Rohit=>Beyond Web-hook Response temporarily saved to Germnay's beyond_response table for reference & to be deleted later
                if($response_flag) {
                    $this->beyond_model->save_beyond_response($_SERVER,'germany');
                    $response_flag=false;
                }
                //Rohit=> Fetch Lead data from mongo
                $lead_record=$this->beyond_model->check_mongo_lead($email,$country);
                if($lead_record>0) {
                    $updt_data=array();
                    $updt_data['beyond_unsubscribed']='1';
                    $updt_data['email_optin_flag']='0';
                    $this->beyond_model->update_mongo_lead_status($email,$updt_data,$country);
                    $updt_data1=array();
                    $updt_data1['email_optin_flag'] = 0;
                    $updt_data1['mle_flag'] = 0;
                    $updt_data1['updated_to_beyond']='-1';
                    $this->beyond_model->update_lead_status($email,$updt_data1,$country);
                    $msg='Beyond Lead Unsubscribed Successfully: LeadInfo=>{Email:'.$email.'}';
                    $status='Success';
                } else {
                    $msg='Beyond Lead Not Found: LeadInfo=>{Email:'.$email.'}';
                    $status='Failed';
                }
            } else {
                if(!in_array($request_ip, $beyonds_ip)) $msg='Unauthorised Beyond Reuest IP-Address=>'.$request_ip;
                else $msg='Beyond Request Fails: Info=>{'.json_encode($_SERVER).'}';
                $status='Failed';
            }
            $desc = array();
            $desc['activity_result']=$status;
            $desc['msg'] = $msg;
            $activity_type='Beyond_Unsubscribed';
            $class_name=$this->router->fetch_class();
            $action_called=$this->uri->uri_string;
            $this->save_loger($desc,$activity_type,$class_name,$action_called,$country);
        }
    }

    // Save logger
    public function save_loger($desc=null,$activity_type=null,$class_name=null,$action_called=null,$country) {   
        $this->load->library("log_writer");
        $this->log_writer->country_construct($country);
        $data = array();
        $date_t=date('Y-m-d H:i:s');
        $data['action_timestamp'] = new MongoDate(strtotime($date_t));
        $data['module_name'] = BEYOND_MODULE;
        $data['class_name'] = $class_name;
        $data['activity_type'] = ($activity_type != null ? $activity_type : BEYOND_SERVICE);
        $data['action_called'] = $action_called;
        $key = $class_name.'/'.$action_called;
        $data['action_url'] = $key;
        $data['action_date'] = new MongoDate();
        $data['ip_address'] = '';
        $data['user_agent'] = '';
        if (!empty($desc)) {
            if (isset($desc['msg']) && $desc['msg'] != '') {
                $data['description'] = $desc['msg'];
            }
            if (isset($desc['activity_result']) && $desc['activity_result'] != '') {
                $data['activity_result'] = $desc['activity_result'];
            }
        }
        $this->log_writer->save_log($data);
    }

    //Rohit=> RED-1712 ReCalling Beyond Registration to send Valid & Eligible Leads, that are remained/skipped because of Master-Slave Slow Replication
    public function recalling_beyond_for_skip_leads($country='') {
        $start = microtime(true);
        $status=$msg='';
        if($country!='') {
            $activity_type='Beyond_Registration';
            $class_name='Beyond_Integration';
            $action_called='recalling_beyond_for_skip_leads';
            $lead_data=$this->beyond_model->get_beyond_valid_skip_bulk_leads($country);
            if(!empty($lead_data)) {
                $lead_count=0;
                foreach($lead_data as $key=>$lead) {
                    $lead_id=isset($lead['lead_id']) ? trim($lead['lead_id']) : '';
                    $email=isset($lead['email']) ? trim($lead['email']) : '';
                    if($lead_id!='') {
                        $this->add_user_to_beyond($lead_id,$country,true);
                        $lead_count++;
                    }
                }
                $msg='Beyond Recall Success: Lead-Processed=>'.$lead_count;
                $status='Success';
            } else {
                $msg='No Data in Beyond Recall..!';
                $status='Failed';
            }
            $desc=array();
            $desc['activity_result']=$status;
            $desc['msg']=$msg;
            $this->save_loger($desc,$activity_type,$class_name,$action_called,$country);
        } else $msg='Invalid Country=>'.$country;
        echo"Time Elapsed=>".$time_elapsed_secs=microtime(true)-$start."\n".$msg;
        exit;
    }

}
