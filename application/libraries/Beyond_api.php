<?php if (!defined('BASEPATH')) exit('No direct script access allowed');
/**
 * CodeIgniter Beyond Integration API Class
 *
 * RED-1637 Sending the Data to Beyond Portal via API
 *
 * @package	CodeIgniter
 * @category	Library
 * @author	Rohit Patil (rohitpatil30) @ Codaemon Softwares, Pune
 */

class Beyond_api {

    private $CI;
    private $country='';
    private $assigned_details_flag=false;
    private $get_details_flag=false;
    private $beyond_credentials=array();
    private $link='';
    private $user='';
    private $secret='';
    private $doi_data_source=array();
    private $soi_data_source=array();

    function __construct($params=array()) {
        $alert_flag=true;
        $this->assigned_details_flag=true;
        $this->CI= & get_instance();
        $this->country=isset($params['country']) ? $params['country'] : '';
        if($this->country!='') {
            $this->CI->config->load('beyond');
            $beyond_config=$this->CI->config->item('env_beyond_details');
            if(isset($beyond_config[ENVIRONMENT]) && !empty($beyond_config[ENVIRONMENT])) {
                //Rohit=>RED-1637 Fetching up Beyond details (like Link, User-name, Data-Sources) as per Environment-wise & Country-wise
                if(isset($beyond_config[ENVIRONMENT][$this->country]) && !empty($beyond_config[ENVIRONMENT][$this->country])) {
                    $alert_flag=false;
                    $this->beyond_credentials=$beyond_config[ENVIRONMENT][$this->country];
                }
            } else $this->assigned_details_flag=false; //Rohit=>To Restrict Alert Emials for Test Environment
        }
        //Rohit=> Sending Alert mail
        if($alert_flag) {
            $beyond_details=array('function'=>'construct');
            $this->send_beyond_alert_mail($beyond_details);
        }
    }

    //Rohit=>Assigning Beyond Credentials to Variables, Earlier it was done in Construct But now need to send data to multiple Beyond Server
    private function assign_beyond_details($beyond_credentials=array()) {
        if(!empty($beyond_credentials)) {
            $this->link=isset($beyond_credentials['link']) ? $beyond_credentials['link'] : '';
            $this->user=isset($beyond_credentials['user_name']) ? $beyond_credentials['user_name'] : '';
            $this->secret=isset($beyond_credentials['secret_key']) ? $beyond_credentials['secret_key'] : '';
            $this->doi_data_source=isset($beyond_credentials['doi_data_source']) ? $beyond_credentials['doi_data_source'] : array();
            if(!is_array($this->doi_data_source)) $this->doi_data_source=(array)$this->doi_data_source;
            $this->soi_data_source=isset($beyond_credentials['soi_data_source']) ? $beyond_credentials['soi_data_source'] : array();
            if(!is_array($this->soi_data_source)) $this->soi_data_source=(array)$this->soi_data_source;
            if($this->link!='' && $this->user!='' && $this->secret!='' && (!empty($this->doi_data_source) || !empty($this->soi_data_source))) {
                $this->get_details_flag=true;
            }
        }
        return $this->get_details_flag;
    }

    //Rohit=>RED-1637 Creating the Beyond Session
    private function get_beyond_session() {
        $session_id=false;
        if($this->get_details_flag) {
            $params=array('user'=>$this->user,'secret'=>$this->secret);
            $resstr=$this->call_beyond_api("login",$params);
            $result=json_decode($resstr);
            if($result->status=="OK") {
                $session_id=$result->session_id;
            } else {
                $beyond_details=array('function'=>'login','params'=>$params,'beyond_result'=>$resstr);
                $this->send_beyond_alert_mail($beyond_details);
            }
        }
        return $session_id;
    }

    //Rohit=>RED-1637 Ending the Created Beyond Session
    private function logout_beyond_session($session_id=false) {
        if($this->get_details_flag && $session_id!=false) {
            $params=array('session_id'=>$session_id);
            $resstr=$this->call_beyond_api("logout",$params);
            $result=json_decode($resstr);
            if($result->status!="OK") {
                $beyond_details=array('function'=>'logout','params'=>$params,'beyond_result'=>$resstr);
                $this->send_beyond_alert_mail($beyond_details);
            }
        }
    }

    //Rohit=>RED-1637 Making Actual API Call to Beyond
    private function call_beyond_api($function_name='',$params=array()) {
        if($this->get_details_flag && $function_name!='') {
            $ch=curl_init($this->link.$function_name);
            curl_setopt($ch, CURLOPT_HEADER, false);
            // Will return the response, if false it print the response
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/json'));
            // Do a post request
            curl_setopt($ch, CURLOPT_POST, true);
            // and finaly the body
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
            // perform curl execuion
            return curl_exec($ch);
        }
    }

    //Rohit=>RED-1637 Creating or Adding New User to Beyond
    public function add_beyond_user($user_data=array(),$soi_doi_flag='soi') {
        $status=false; $res=array();
        foreach($this->beyond_credentials as $beyond_server=>$beyond_credentials) {
            if($this->assign_beyond_details($beyond_credentials)) {
                $alert_flag=$status_flag=false;
                $success_data_source=$fail_data_source=$params=$resstr=array();
                $session_id=$this->get_beyond_session();
                if($session_id!=false && !empty($user_data)) {
                    if($soi_doi_flag=='doi') $data_source=$this->doi_data_source;
                    else $data_source=$this->soi_data_source;
                    foreach($data_source as $data_source_id) {
                        if($data_source_id!='') {
                            $beyond_data=array();
                            $beyond_data[]=$user_data;
                            $params=array('session_id'=>$session_id,'recipients'=>$beyond_data,'source_id'=>$data_source_id,'return_ids'=>1);
                            $resstr=$this->call_beyond_api("modcreaterecipients",$params);
                            $result=json_decode($resstr);
                            if($result->status=="OK") {
                                $status=$status_flag=true;
                                $beyond_id='';
                                $beyond_res=$result->ids;
                                foreach($beyond_res[0] as $email=>$beyond_ids) {
                                    $beyond_id=$beyond_ids;
                                }
                                $success_data_source[$data_source_id]=$beyond_id;
                            } else {
                                $alert_flag=true;
                                $fail_data_source[]=$data_source_id;
                            }
                        }
                    }
                    $this->logout_beyond_session($session_id);
                } else $alert_flag=true;
                if($alert_flag) {
                    $beyond_details=array('function'=>'createrecipients','params'=>$params,'beyond_result'=>$resstr,'data_source_type'=>$soi_doi_flag,'data_source'=>$fail_data_source);
                    $this->send_beyond_alert_mail($beyond_details);
                }
                $res[$soi_doi_flag][$beyond_server]=$success_data_source;
                if(!empty($fail_data_source) && $alert_flag) $res[$soi_doi_flag][$beyond_server]['failed']=$fail_data_source;
            } else $res[$soi_doi_flag][$beyond_server]='no-details';
        }
        $result=array('status'=>$status,'result'=>$res);
        return $result;
    }

    //Rohit=>RED-1637 Get Beyond User Id according to Data Source
    private function get_beyond_user_id($user_email,$data_source_id,$session_id=null) {
        if($this->get_details_flag) {
            $beyond_user=false;
            if($user_email!='' && $data_source_id!='') {
                if(!is_array($user_email)) $user_email=(array)$user_email;
                $new_session=false;
                if($session_id==null) {
                    $new_session=true;
                    $session_id=$this->get_beyond_session();
                }
                $params=array('session_id'=>$session_id,'emails'=>$user_email,'source_id'=>$data_source_id);
                $resstr=$this->call_beyond_api("getrecipients",$params);
                $result=json_decode($resstr);
                if($result->status=="OK") {
                    $beyond_user=$result->recipients;
                } else {
                    $beyond_details=array('function'=>'getrecipients','params'=>$params,'beyond_result'=>$resstr);
                    $this->send_beyond_alert_mail($beyond_details);
                }
                if($new_session==true) $this->logout_beyond_session($session_id);
            }
            return $beyond_user;
        } else return 'no-details';
    }
    
    //Rohit=>RED-1637 Updating Beyond User details (like unsubscribed done from system or fields update)
    public function update_beyond_user($user_data=array(),$email='',$soi_doi_flag='soi',$type='') {
        $status=false; $res=array();
        foreach($this->beyond_credentials as $beyond_server=>$beyond_credentials) {
            if($this->assign_beyond_details($beyond_credentials)) {
                $alert_flag=$status_flag=false;
                $success_data_source=$fail_data_source=$params=$resstr=array();
                $session_id=$this->get_beyond_session();
                //$beyond_user_id=(isset($user_data['member_id']) && $user_data['member_id']!='') ? $user_data['member_id'] : '';
                if($email=='') $email=($type=='beyond_data_update' && isset($user_data['email'])) ? $user_data['email'] : '';
                if($session_id!=false && !empty($user_data) && $email!='') {
                    if($soi_doi_flag=='doi') $data_source=$this->doi_data_source;
                    else $data_source=$this->soi_data_source;
                    foreach($data_source as $data_source_id) {
                        if($data_source_id!='') {
                            $beyond_user=$this->get_beyond_user_id($email,$data_source_id,$session_id);
                            $beyond_user_id=isset($beyond_user[0]->member_id) ? $beyond_user[0]->member_id : '';
                            if($beyond_user_id!='' || $type=='beyond_data_update') {
                                if($type=='beyond_data_update' && $beyond_user_id=='') unset($user_data['member_id']);
                                else $user_data['member_id']=$beyond_user_id;
                                $beyond_data=array();
                                $beyond_data[]=$user_data;
                                $params=array('session_id'=>$session_id,'recipients'=>$beyond_data,'source_id'=>$data_source_id,'return_ids'=>1);
                                $resstr=$this->call_beyond_api("modcreaterecipients",$params);
                                $result=json_decode($resstr);
                                if($result->status=="OK") {
                                    $status=$status_flag=true;
                                    $beyond_id='';
                                    if(!empty($result->ids)) {
                                        $beyond_res=$result->ids;
                                        foreach($beyond_res[0] as $email=>$beyond_ids) {
                                            $beyond_id=$beyond_ids;
                                        }
                                    } else $beyond_id=$beyond_user_id;
                                    $success_data_source[$data_source_id]=$beyond_id;
                                } else {
                                    $alert_flag=true;
                                    $fail_data_source[]=$data_source_id;
                                }
                            }
                        }
                    }
                    $this->logout_beyond_session($session_id);
                } else $alert_flag=true;
                if($alert_flag) {
                    if($type=='beyond_data_update') $func_name='modifyrecipients';
                    else $func_name='unsubscribedrecipients';
                    $beyond_details=array('function'=>$func_name,'params'=>$params,'beyond_result'=>$resstr,'data_source_type'=>$soi_doi_flag,'data_source'=>$fail_data_source);
                    $this->send_beyond_alert_mail($beyond_details);
                }
                if($type=='beyond_data_update') $res[$soi_doi_flag][$beyond_server]=$success_data_source;
                else $res[$soi_doi_flag][$beyond_server]=$status_flag;
                if(!empty($fail_data_source) && $alert_flag) $res[$soi_doi_flag][$beyond_server]['failed']=$fail_data_source;
            } else $res[$soi_doi_flag][$beyond_server]='no-details';
        }
        $result=array('status'=>$status,'result'=>$res);
        return $result;
    }

    //Rohit=>RED-1637 Blacklist Beyond User
    public function blacklist_beyond_user($user_data=array(),$email='',$soi_doi_flag='soi') {
        $status=false; $res=array();
        foreach($this->beyond_credentials as $beyond_server=>$beyond_credentials) {
            if($this->assign_beyond_details($beyond_credentials)) {
                $alert_flag=$status_flag=false;
                $fail_data_source=$params=$resstr=array();
                $session_id=$this->get_beyond_session();
                if($session_id!=false && !empty($user_data) && $email!='') {
                    if($soi_doi_flag=='doi') $data_source=$this->doi_data_source;
                    else $data_source=$this->soi_data_source;
                    foreach($data_source as $data_source_id) {
                        if($data_source_id!='') {
                            $beyond_data=array();
                            $beyond_data[0]=array('emails'=>$email);
                            $beyond_data[1]=$user_data;
                            $params=array('session_id'=>$session_id,'emails'=>$beyond_data,'source_id'=>$data_source_id);
                            $resstr=$this->call_beyond_api("addtoblacklist",$params);
                            $result=json_decode($resstr);
                            if($result->status=="OK") {
                                $status=$status_flag=true;
                            } else {
                                $alert_flag=true;
                                $fail_data_source[]=$data_source_id;
                            }
                        }
                    }
                    $this->logout_beyond_session($session_id);
                } else $alert_flag=true;
                if($alert_flag) {
                    $beyond_details=array('function'=>'addtoblacklist','params'=>$params,'beyond_result'=>$resstr,'data_source_type'=>$soi_doi_flag,'data_source'=>$fail_data_source);
                    $this->send_beyond_alert_mail($beyond_details);
                }
                $res[$soi_doi_flag][$beyond_server]=$status_flag;
                if(!empty($fail_data_source) && $alert_flag) $res[$soi_doi_flag][$beyond_server]['failed']=$fail_data_source;
            } else $res[$soi_doi_flag][$beyond_server]='no-details';
        }
        $result=array('status'=>$status,'result'=>$res);
        return $result;
    }

    //Rohit=>RED-1637 Function to Send Alert Mail if Beyond API fails
    private function send_beyond_alert_mail($beyond_details=array()) {
        if($this->assigned_details_flag) {
        $function=isset($beyond_details['function']) ? $beyond_details['function'] : '';
        $params=isset($beyond_details['params']) ? json_encode($beyond_details['params']) : '';
        $beyond_result=isset($beyond_details['beyond_result']) ? json_encode($beyond_details['beyond_result']) : '';
        $data_source_type=isset($beyond_details['data_source_type']) ? $beyond_details['data_source_type'] : '';
        $data_source=isset($beyond_details['data_source']) ? json_encode($beyond_details['data_source']) : ($data_source_type=='doi' ? json_encode($this->doi_data_source) : ($data_source_type=='soi' ? json_encode($this->soi_data_source) : ''));
        $msg="Beyond API Fails for Country: ".$this->country.":-<br>GetDetails: ".$this->get_details_flag."<br>Beyond_Link: ".$this->link."<br>Beyond_Func: ".$function."<br>Data_Source_Type: ".$data_source_type."<br>Data_Sources: ".$data_source."<br>Beyond_params: ".$params."<br>Beyond_Result: ".$beyond_result;
        $this->CI->load->library("Log_tracker");
        //$this->CI->log_tracker->country_construct($this->country);
        $this->title='Beyond API Fails..!';
        $this->id=$data_source_type.'->Data-Sources='.$data_source;
        $this->status_title=FAIL;
        $this->message_title=$this->title;
        $this->module_title=BEYOND_MODULE;
        $this->class_title=$this->CI->router->fetch_class();
        $this->method_title=$this->CI->router->fetch_method();
        $this->activity_title=BEYOND_SERVICE;
        $this->status_title=FAIL;
        $this->message_title=$msg;
        $this->action_url=FCPATH.'/'.$this->CI->uri->uri_string;
        $this->CI->log_tracker->send_alert_mail($this->title,$this->id,$this->module_title,$this->class_title,$this->method_title,$this->activity_title,$this->status_title,$this->message_title,null,$this->action_url,array(),$this->country);
        }
    }
}
