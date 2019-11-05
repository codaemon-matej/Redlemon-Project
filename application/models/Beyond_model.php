<?php if (!defined('BASEPATH')) exit('No direct script access allowed');
/**
 * CodeIgniter Model file for Integration of Beyond API
 *
 * @package	CodeIgniter
 * @category	Model
 * @author	Rohit Patil (rohitpatil30) @ Codaemon Softwares, Pune
 */

class beyond_model extends CI_model {

    function __construct() {
        parent::__construct();
    }

    //Rohit=>RED-1637 Get data of Beyond ready to add Lead
    public function get_beyond_ready_user_details($lead_id='',$country='') {
        $lead_data=array();
        if($country!='' && $lead_id!='') {
            $this->read_db = $this->load->database($this->config->item('read',$country), TRUE);
            $query=$this->read_db->query("SELECT lead_id,salutation,first_name,last_name,email,gender,beyond_user_id, DATE_FORMAT(date_of_birth,'%Y-%m-%d %H:%i:%s') as date_of_birth,double_optin_flag,duplicate_flag,updated_to_beyond, static_field_3,marketing_partner_id FROM lead_info WHERE lead_id='".$lead_id."' AND duplicate_flag!='1' AND is_valid='1' AND mmpg_return_code='2' AND email_optin_flag='1' AND updated_to_beyond IN ('0','1','3')");
            $lead_data=$query->row_array();
        }
        return $lead_data;
    }

    //Rohit=>RED-1637 Get data of already added lead to Beyond
    public function get_user_details($lead_id='',$country='') {
        $lead_data=array();
        if($country!='' && $lead_id!='') {
            $this->read_db = $this->load->database($this->config->item('read',$country), TRUE);
            $query=$this->read_db->query("SELECT lead_id,email,beyond_user_id,double_optin_flag,updated_to_beyond FROM lead_info WHERE lead_id='".$lead_id."' AND duplicate_flag!='1' AND updated_to_beyond!='0' ");
            $lead_data=$query->row_array();
        }
        return $lead_data;
    }

    //Rohit=>RED-1712 Get Beyond Valid Buld Leads that are skipped because of Master-Slave Replication
    public function get_beyond_valid_skip_bulk_leads($country='') {
        $lead_data=array();
        if($country!='') {
            $this->read_db=$this->load->database($this->config->item('read',$country), TRUE);
            $this->read_db->select('lead_id,email');      
            $this->read_db->from(LEAD_INFO);
            $this->read_db->where("(date_added >= DATE_SUB(NOW(),INTERVAL 10 HOUR))");
            $this->read_db->where('duplicate_flag !=',1);
            $this->read_db->where('is_valid',1);
            $this->read_db->where('mmpg_return_code',2);
            $this->read_db->where('email_optin_flag',1);
            $this->read_db->where("(updated_to_beyond='0' OR (double_optin_flag='1' AND updated_to_beyond='3'))");
            $this->read_db->order_by('id','ASC');
            $this->read_db->limit('300');
            $lead_data=$this->read_db->get()->result_array();
        }
        return $lead_data;
    }

    // Update lead status in lead_info table
    public function update_lead_status($email='',$data=array(),$country='') {
        if($country!='' && $email!='' && !empty($data)) {
            $this->write_db = $this->load->database($this->config->item('write',$country), TRUE);
            $this->write_db->where('email', $email);
            $this->write_db->update(LEAD_INFO, $data);
            return true;
        } else return false;
    }

    /* Rohit=>RED-1637 Beyond Status (updated_to_beyond) in lead_info tabel
     *  0=> initial & remain to send to Beyond
     *  1=> found Valid & send to Beyond DOI Data Sources & Unsubscribed from SOI if added earlier
     *  2=> unsubscribed from Back-Office & so in Beyond as well
     *  3=> found Valid & send to Beyond SOI Data Sources
     *  4=> blacklisted from Back-Office & so in Beyond as well
     * -1=> unsubscribed from Beyond & so in System as well
    */
    public function update_lead_beyond_sent_status($lead_id='',$country='',$beyond_flag='') {
        if($country!='' && $lead_id!='' && $beyond_flag!='') {
            $this->write_db = $this->load->database($this->config->item('write',$country), TRUE);
            $data['updated_to_beyond'] = $beyond_flag;
            $this->write_db->where('lead_id', $lead_id);
            $this->write_db->update('lead_info', $data);
            return true;
        } else return false;
    }

    // check if Lead Present in MondoDB
    public function check_mongo_lead($email='',$country='') {
        if($country!='' && $email!='') {
            $this->mongo_db = new Mongo_db($country.'_mongo');
            return $this->mongo_db->select('id')->where('email.email_id',$email)->count('leads');
        } else return 0;
    }

    // Update lead status in MondoDB
    public function update_mongo_lead_status($email='',$update_data=array(),$country='') {
        if($country!='' && $email!='' && !empty($update_data)) {
            $this->mongo_db = new Mongo_db($country.'_mongo');
            $mongo_date = $this->mongo_db->date(strtotime(date('Y-m-d H:i:s')));
            $session_id = substr(sha1(rand()), 0, 16);
            $history_data = array();
            $history_data['email']= array('update_source'=>'system', 'email_id'=>$email, 'email_optin_flag'=>'0', 'update_reason'=>'Beyond Unsubscribed', 'date_inserted'=>$mongo_date, 'date_modified'=>$mongo_date);
            $history_data['registration_history']= array('update_source'=>'system', 'email'=>$email, 'email_optin_flag'=>'0', 'update_reason'=>'Beyond Unsubscribed', 'last_action'=>'Beyond Unsubscribed', 'session_id'=>$session_id, 'admin_session_id'=>$session_id, 'date_inserted'=>$mongo_date, 'date_modified'=>$mongo_date);
            $this->mongo_db->where('email.email_id',$email)->set($update_data)->push($history_data)->update('leads');
            return true;
        } else return false;
    }

    //Rohit=>RED-1672 Fetching up Marketing Partner Deatils or Data
    public function get_marketing_partner_details($marketing_partner_id='') {
        $marketing_partner_data=array();
        if($marketing_partner_id!='') {
            //$this->global_read_db=$this->load->database('global_read', TRUE);
            $this->global_read_db = $this->load->database($this->config->item('read','global'), TRUE);
            $this->global_read_db->select('id,client_name');
            $this->global_read_db->where('id',$marketing_partner_id);
            $this->global_read_db->where('status',1);
            $this->global_read_db->order_by('id','DESC');
            $marketing_partner_data=$this->global_read_db->get(GLOBAL_PARTNERS)->row_array();
        }
        return $marketing_partner_data;
    }

    // Save beyond response in database is temporary & removed it later on
    public function save_beyond_response($server_data=array(),$country='') {
        if($country!='' && !empty($server_data)) {
            $this->write_db = $this->load->database($this->config->item('write',$country), TRUE);
            $insert_data['response'] = json_encode($server_data);
            $this->write_db->insert('beyond_response', $insert_data);
            return true;
        } else return false;
    }

}
