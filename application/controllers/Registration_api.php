<?php if(!defined('BASEPATH')) exit('No direct script access allowed');
/**
 * CodeIgniter Generating Leads from Third party API Class
 *
 * Fetching the Lead Data Array for Particular Country via API
 *
 * @package	CodeIgniter
 * @category	Controller
 * @author	Rohit Patil (rohitpatil30) @ Codaemon Softwares, Pune
 */

class Registration_API extends CI_Controller {

    function __construct() {
        parent::__construct();
    }

    //Rohit=> Fetch the Lead data via API
    public function lead_registration() {
        $country_list=$leads=$response=$api_log=array(); //Rohit=> $api_log stores the api processed results log in registration_api_log table
        $status=$reason=$simple_msg=$request_ip='';
        $api_response=json_encode($_SERVER); //Rohit=> to save whole api-response in registration_api_log table

        //Rohit=> Breaking the URL to get parameters (Marketing-Campaigns-Id, Partner-Id & Sweepsatke-Id & Country)
        $marketing_campaign_id=$marketing_partner_id=$sweepstake_id=$country='';
        $json_check=$url_flag=false;
        if(isset($_GET['APICAMPGID']) && trim($_GET['APICAMPGID'])!='') {
            $param_arr=explode('-',trim($_GET['APICAMPGID']));
            $marketing_campaign_id=(isset($param_arr[0]) && $param_arr[0]>0) ? $param_arr[0] : '';
            $marketing_partner_id=(isset($param_arr[1]) && $param_arr[1]>0) ? $param_arr[1] : '';
            $sweepstake_id=(isset($param_arr[2]) && $param_arr[2]>0) ? $param_arr[2] : '';
            $country=(isset($param_arr[3]) && $param_arr[3]!='') ? $param_arr[3] : '';
            $json_check=(isset($param_arr[4]) && strtolower($param_arr[4])=='json') ? true : false;
            if($marketing_campaign_id!='' && $marketing_partner_id!='' && $sweepstake_id!='' && $country!='') {
                $local_config=$this->config->config;
                $country_list=(isset($local_config['countries']) && !empty($local_config['countries'])) ? array_diff($local_config['countries'], array('utc')) : $local_config['countries'];
                if(in_array($country,$country_list)) $url_flag=true;
            }
        }

        //Rohit=> If JSON text passed in URL, then fetch data according to JSON POST Method otherwise using GET Method
        if($json_check) { //Rohit=> Using JSON POST Method
            $postdata=file_get_contents('php://input');
            if(!empty($postdata)) $post_data=json_decode($postdata,true);
            $leads=(isset($post_data['leads']) && !empty($post_data['leads'])) ? $post_data['leads'] : array();
        } else { //Rohit=> using GET Method
            $tmp_leads=array();
            foreach($_GET as $key=>$val) {
                if($key!='APICAMPGID') $tmp_leads[$key]=$val;
            }
            $leads[]=$tmp_leads;
            $postdata=isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '';
        }
        $api_response.='\n\nLead-Data=>'.$postdata; //Rohit=> to save lead_data as well in registration_api_log table

        if(!empty($leads) && $url_flag) {
            $this->config->load('registration_api');

            //Rohit=> Checking & Validating Country Code or text
            $reg_api_config=$this->config->item('allowed_country_api_lead_generation');
            $proceed=array_key_exists($country,$reg_api_config) ? true : false;

            //Rohit=> Checking & Validating (Whilte-list) IP Addresses
            $authorised_api_ip=isset($reg_api_config[$country]['api_ip']) ? (array)$reg_api_config[$country]['api_ip'] : array();
            $request_ip=(isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR']!='' ? trim($_SERVER['REMOTE_ADDR']) : "");
            $api_ip_allowed=(!empty($authorised_api_ip)) ? (in_array($request_ip,$authorised_api_ip) ? true : false) : true;

            //Rohit=> Checking if the API Url/Link Parameters are Genuine or Marketing Campaigns Records exists or not
            $table_name='marketing_campaigns';
            $column_name='COUNT(marketing_campaigns.id) as tot_cnt, marketing_campaigns.max_cap_day,marketing_campaigns.max_cap_added_date,marketing_campaigns.max_cap_day_left';
            $where=array('marketing_campaigns.id'=>$marketing_campaign_id,'marketing_campaigns.marketing_partner_id'=>$marketing_partner_id,'marketing_campaigns.status'=>'1','campaign_templates.sweepstake_id'=>$sweepstake_id);
            $join=array('id'=>array('table_name'=>'campaign_templates','field_name'=>'campaign_id'));
            $marketing_campaign_record=get_customised_table_data($country,$table_name,$column_name,$where,array(),array(),$join,'','',array(),'row');
            $url_param_flag=(isset($marketing_campaign_record['tot_cnt']) && $marketing_campaign_record['tot_cnt']>0) ? true : false;
            //Rohit=> Checking for Marketing Campaigns Max-Caps, if been set-up in local-config
            $marketing_campaigns_max_caps_proceed=true;
            if($url_param_flag && isset($local_config['allowed_country_marketing_campaign_max_cap_deduction'][$country])) {
                $max_cap_day=isset($marketing_campaign_record['max_cap_day']) ? $marketing_campaign_record['max_cap_day'] : 0;
                if($max_cap_day>0) {
                    $curr_date=date('Y-m-d');
                    $curr_dt=date('Y-m-d H:i:s');
                    $max_cap_day_left=isset($marketing_campaign_record['max_cap_day_left']) ? $marketing_campaign_record['max_cap_day_left'] : 0;
                    $max_cap_added_date=isset($marketing_campaign_record['max_cap_added_date']) ? date('Y-m-d',strtotime($marketing_campaign_record['max_cap_added_date'])) : $curr_date;
                    if($max_cap_added_date!=$curr_date) {
                        //Rohit=> If another day, then Reset the Daily Max-Caps
                        $this->load->model('marketing/marketing_model');
                        $this->marketing_model->country_construct($country);
                        $updt_data=array();
                        $updt_data['max_cap_day_left']=$max_cap_day;
                        $updt_data['max_cap_added_date']=$curr_dt;
                        $this->marketing_model->update_marketing_campaign($marketing_campaign_id,$updt_data);

                        //Rohit=> Mail the Previous Max-Caps Status/Values for record
                        $subject='Marketing Campaigns ('.$marketing_campaign_id.') Max-Caps Status ('.$country.')';
                        $mailBody="<br/> Marketing Campaigns Max-Caps Status :- <br/>";
                        $mailBody.="Marketing Campaign Id : ".$marketing_campaign_id."<br/>";
                        $mailBody.="Date : ".$max_cap_added_date."<br/>";
                        $mailBody.="Max-Caps Left : ".$max_cap_day_left."<br/>";
                        $mailBody.="Max-Caps Set : ".$max_cap_day."<br/>";
                        $mailBody.="Country : ".$country."<br/>";
                        $mailBody.="Activity Url : ".$this->uri->config->config['base_url'].$this->uri->uri_string."<br/>";
                        $sender=$local_config['alert_email_sender'];
                        $recipients=implode(',',$local_config['alert_email_recipients']);
                        $headers="MIME-Version: 1.0\r\n";
                        $headers.="Content-type: text/html; charset=iso-8859-1\r\n";
                        $headers.="From:".$sender."\r\n";
                        mail($recipients,$subject,$mailBody,$headers);
                    } else if($max_cap_day_left<=0) $marketing_campaigns_max_caps_proceed=false; //Rohit=> Marketing Campaigns Max-Caps Reached
                }
            }

            //Rohit=> Checking & Validating API url/link & its Parameters, country, API IP and Marketing Campaigns Max-Caps
            if($url_param_flag && $proceed && $api_ip_allowed && $marketing_campaigns_max_caps_proceed) {
                $params=array('country'=>$country,'leads'=>$leads,'json_check'=>$json_check);
                if(isset($reg_api_config[$country]['doi_email']['after_valid_acceptance'])) {
                    $params['doi_email_after_valid_acceptance']=$reg_api_config[$country]['doi_email']['after_valid_acceptance'];
                }
                if(isset($reg_api_config[$country]['mmpg_validation'])) {
                    $params['mmpg_validation']=$reg_api_config[$country]['mmpg_validation'];
                }
                if(isset($reg_api_config[$country]['update_accepted_lead_as_valid'])) {
                    $params['update_accepted_lead_as_valid']=$reg_api_config[$country]['update_accepted_lead_as_valid'];
                }
                if(isset($reg_api_config[$country]['beyond_registration'])) {
                    $params['beyond_registration']=$reg_api_config[$country]['beyond_registration'];
                }
                if(isset($reg_api_config[$country]['deduct_marketing_caps_after_acceptance'])) {
                    $params['deduct_marketing_caps_after_acceptance']=$reg_api_config[$country]['deduct_marketing_caps_after_acceptance'];
                }
                if(isset($reg_api_config[$country]['reject_duplicate'])) {
                    $params['reject_duplicate']=$reg_api_config[$country]['reject_duplicate'];
                }
                if(isset($reg_api_config[$country]['constants'])) {
                    $sweepstake_url=(isset($_SERVER['HTTP_HOST']) && isset($_SERVER['REQUEST_URI'])) ? $_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'] : '';
                    $constants=(array)$reg_api_config[$country]['constants'];
                    if($marketing_campaign_id!='') $constants['marketing_campaign_id']=$marketing_campaign_id;
                    if($marketing_partner_id!='') $constants['marketing_partner_id']=$marketing_partner_id;
                    if($sweepstake_id!='') $constants['sweepstake_id']=$sweepstake_id;
                    if($sweepstake_url!='') $constants['sweepstake_url']=$sweepstake_url;
                    $params['constants']=$constants;
                }
                if(isset($reg_api_config[$country]['mandatory_fields'])) {
                    $params['mandatory_fields']=(array)$reg_api_config[$country]['mandatory_fields'];
                }
                if(isset($reg_api_config[$country]['phone_type'])) {
                    $params['phone_type']=(array)$reg_api_config[$country]['phone_type'];
                }
                if(isset($reg_api_config[$country]['fields_validation'])) {
                    $params['fields_validation']=(array)$reg_api_config[$country]['fields_validation'];
                }
                $params['lead_processing']=true;
                $this->load->library("Lead_registration_process",$params);
                $registration_result=$this->lead_registration_process->registration_result();
                $response=isset($registration_result['process_result']) ? $registration_result['process_result'] : '';
                $api_log=isset($registration_result['process_log']) ? $registration_result['process_log'] : array();
                $status=(isset($response['status']) && $response['status']==true) ? 'Success' : 'Failed';
                $reason=isset($registration_result['final_reason']) ? $registration_result['final_reason'] : '';
                $simple_msg=isset($registration_result['simple_msg']) ? $registration_result['simple_msg'] : '';
                if(!isset($api_log[0]['api_response']) || (isset($api_log[0]['api_response']) && $api_log[0]['api_response']=='')) $api_log[0]['api_response']=$api_response;
                if(!isset($api_log[0]['marketing_partner_id']) || (isset($api_log[0]['marketing_partner_id']) && $api_log[0]['marketing_partner_id']=='')) $api_log[0]['marketing_partner_id']=$marketing_partner_id;
                if(!isset($api_log[0]['marketing_campaign_id']) || (isset($api_log[0]['marketing_campaign_id']) && $api_log[0]['marketing_campaign_id']=='')) $api_log[0]['marketing_campaign_id']=$marketing_campaign_id;
            } else {
                $status='Failed';
                if(!$proceed) $reason='API Lead Registration Not Allowed..!';
                else if(!$api_ip_allowed) $reason='Reponse IP=>'.$request_ip.' Not Allowed..!';
                else if(!$url_param_flag) $reason='Wrong URL Parameters..!';
                else if(!$marketing_campaigns_max_caps_proceed) $reason='Daily Max-Caps Reached..!';
                else $reason='Improper Response Data..!';
                $response['status']=false;
                $response['msg']=$simple_msg=$reason;
                $api_log[]=array('marketing_partner_id'=>$marketing_partner_id,'marketing_campaign_id'=>$marketing_campaign_id,'api_response'=>$api_response,'reason'=>$reason);
            }
            if($country!='' && !empty($api_log)) {
                $this->load->model("lead_model");
                $this->lead_model->country_construct($country);
                $this->lead_model->save_registration_api_log($api_log);
            }
        } else {
            $status='Failed';
            if(!$url_flag) $reason='Wrong URL..!';
            else $reason='Improper Response..!';
            $response['status']=false;
            $response['msg']=$simple_msg=$reason;
        }

        if($json_check) {
            header('Content-type: application/json');
            echo json_encode($response);
        } else {
            echo ($simple_msg!='' ? $simple_msg : $reason);
        }

        //Rohit=> Maintaining API Request Log in Module Log
        $this->load->library("log_writer");
        $log_country=($country!='' && in_array($country,$country_list)) ? $country : 'australia';
        $this->log_writer->country_construct($log_country);
        $log_data=array();
        $curr_date=date('Y-m-d H:i:s');
        $log_data['action_timestamp']=new MongoDate(strtotime($curr_date));
        $log_data['module_name']=REGISTRATION_API_MODULE;
        $log_data['activity_type']=LEAD_DATA_COLLECTION;
        $log_data['class_name']=$this->router->fetch_class();
        $log_data['action_called']=$this->router->fetch_method();
        $log_data['action_url']=$this->uri->config->config['base_url'].(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : $this->uri->uri_string);
        $log_data['action_date']=new MongoDate();
        $log_data['ip_address']=$request_ip;
        $log_data['user_agent']='API';
        $log_data['description']=$reason;
        $log_data['activity_result']=$status;
        $this->log_writer->save_log($log_data);

    } //Rohit=> lead_registration() Ends

}

