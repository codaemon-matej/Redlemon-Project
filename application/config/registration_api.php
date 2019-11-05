<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Configuration File for Lead registration/Generation via Third Party API
 * Rohit=> Assigning Country-wise Constants & congigurations for Lead Registartion via API
 */

$config['allowed_country_api_lead_generation'] = array(
    'australia'=>array( //Rohit=> uncommented country are allowed for API Lead Registration & commented menas not
	//Rohit=> API IP-Address allowed, blank means all allowed
        'api_ip'=>array('182.48.243.176','123.252.183.73', //Rohit=> Codaemons IPs
            '52.28.251.145', //Rohit=> TEST Server IPs (required when triggering CSV Data to API)
	),
        'api_report_email_template_id'=>array('aws'=>'27','test'=>'25','development'=>'25'), //Rohit=> Setup & assign the API Report's Email-Template-Id here Environment-wise
        'doi_email'=>array('after_valid_acceptance'=>false,'after_valid_mmpg'=>false), //Rohit=> Actions after which to Trigger DOI Email to Leads
        'mmpg_validation'=>false,
        'update_accepted_lead_as_valid'=>true,
        'beyond_registration'=>true,
        'deduct_marketing_caps_after_acceptance'=>true,
        'reject_duplicate'=>true,
        'constants'=>array( //Rohit=> Defining constants that will be assigned to the fetched record
            'filter_type'=>'Sponsoring', //Rohit=> filter to identifies such record & stored in lead_info->static_field_3 column
            'registration_type'=>'full', //Rohit=> default registration_type of records, if send via API then that value will be considered
            'sweepstake_url'=>'http://www.rlmconsole.de', //Rohit=> will consider this for DOI email
            'domain'=>'www.rlmconsole.de',
            'sub_marketing_partner_id'=>'',
            'campaign_id'=>'',
            'publisher_id'=>'',

            //Rohit=> RED-1599 Marketing-Campaigns-Id,Partner-Id & Sweepstake-Id are fetched from Marketing Campaigns API generated url
            'marketing_campaign_id'=>'9999', //Rohit=>The marketing_campaigns=>platform_type must have sponsoring value
            'marketing_partner_id'=>'9999',
            'sweepstake_id'=>'9999', //Rohit=>The sweepstakes=>type must have sponsoring value & assign sponsors to this & imprint_url page if used in email template
        ),
        //'mandatory_fields'=>array('salutation','first_name','last_name','email','date_of_birth','post_code','house_number','street','city','ip_address'),
        'mandatory_fields'=>array('salutation','first_name','last_name','email','date_of_birth','ip_address'),
        'phone_type'=>array(
            'allowed_phone_digits'=>'9',
            'area_code_check'=>false, //Rohit=> true means area_code will be considered else not
            'allowed_area_code_digits'=>'', //Rohit=> will considered only when area_code_check is true and blank means allow any
            'area_code_not_allowed'=>array('0700','0800','0900','0190'),
            'landline_access_codes'=>array('2','3','7','8'),
            'mobile_access_codes'=>array('4'),
            'country_code_check'=>true, //Rohit=> true means it will remove country code from Phone Number
            'country_code'=>'61', //Rohit=> will considered only when country_code_check is true
        ),
        'fields_validation'=>array( //Rohit=> Add field-names to specify validation criteria's, if field not added will take default validation, & if kept blank then will skip its validation
            'phone'=>array(
                'min_length'=>9,
                'max_length'=>9,
                'start_with'=>'',
            ),
            'post_code'=>array(
                'min_length'=>4,
                'max_length'=>4,
                'start_with'=>'',
            ),
        ),
    ),
    'uk'=>array( //Rohit=> uncommented country are allowed for API Lead Registration & commented menas not
	//Rohit=> API IP-Address allowed, blank means all allowed
        'api_ip'=>array('182.48.243.176','123.252.183.73', //Rohit=> Codaemons IPs
	),
        'api_report_email_template_id'=>array('aws'=>'25','test'=>'26','development'=>'26'), //Rohit=> Setup & assign the API Report's Email-Template-Id here Environment-wise
        'doi_email'=>array('after_valid_acceptance'=>false,'after_valid_mmpg'=>false), //Rohit=> Actions after which to Trigger DOI Email to Accepted Leads
        'mmpg_validation'=>false,
        'update_accepted_lead_as_valid'=>true,
        'beyond_registration'=>true,
        'deduct_marketing_caps_after_acceptance'=>true,
        'reject_duplicate'=>true,
        'constants'=>array( //Rohit=> Defining constants that will be assigned to the fetched record
            'filter_type'=>'Sponsoring', //Rohit=> filter to identifies such record & stored in lead_info->static_field_3 column
            'registration_type'=>'full', //Rohit=> default registration_type of records, if send via API then that value will be considered
            'sweepstake_url'=>'http://www.rlmconsole.de', //Rohit=> will consider this for DOI email
            'domain'=>'www.rlmconsole.de',
            'sub_marketing_partner_id'=>'',
            'campaign_id'=>'',
            'publisher_id'=>'',

            //Rohit=> RED-1599 Marketing-Campaigns-Id,Partner-Id & Sweepstake-Id are fetched from Marketing Campaigns API generated url
            'marketing_campaign_id'=>'9999', //Rohit=>The marketing_campaigns=>platform_type must have sponsoring value
            'marketing_partner_id'=>'9999',
            'sweepstake_id'=>'9999', //Rohit=>The sweepstakes=>type must have sponsoring value & assign sponsors to this & imprint_url page if used in email template
        ),
        'mandatory_fields'=>array('salutation','first_name','last_name','email','date_of_birth','ip_address'),
        'phone_type'=>array(
            'allowed_phone_digits'=>'12',
            'area_code_check'=>false, //Rohit=> true means area_code will be considered else not
            'allowed_area_code_digits'=>'', //Rohit=> will considered only when area_code_check is true and blank will allow any
            'area_code_not_allowed'=>array('0700','0800','0900','0190'),
            'landline_access_codes'=>array('1','2','3','500','80','84','87','9'),
            'mobile_access_codes'=>array('7'),
            'country_code_check'=>true, //Rohit=> true means it will remove country code from Phone Number
            'country_code'=>'44', //Rohit=> will considered only when country_code_check is true
        ),
        'fields_validation'=>array( //Rohit=> Add field-names to specify validation criteria's, if field not added will take default validation, & if kept blank then will skip its validation
        ),
    ),
    //'germany'=>array(), //Rohit=> uncommented country are allowed for API Lead Registration & commented menas not
);
