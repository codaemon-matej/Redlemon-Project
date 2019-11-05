<?php  if(!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Configuration File for Beyond API
 * Rohit=> Assigning Enviroment-wise & Country-wise Beyond details
 */

//Rohit=>RED-1637 Environment-wise & country-wise Beyond details (so that Test leads cann't be send)
$config['env_beyond_details'] = array(
    'whm'=>array(
        'germany'=>array(
            'em6'=>array(
                'link'=>'https://em6.beyondrm.com/XXXX/',
                'user_name'=>'XXXX',
                'secret_key'=>'XXXX',
                'doi_data_source'=>array(3,8,9,10),
                'soi_data_source'=>5
            ),
        ),
    ),
    'aws'=>array(
        'germany'=>array(
            'em6'=>array(
                'link'=>'https://em6.beyondrm.com/XXXX/',
                'user_name'=>'XXXX',
                'secret_key'=>'XXXX',
                'doi_data_source'=>array(3,8,9,10),
                'soi_data_source'=>5
            ),
        ),
        'australia'=>array(
            'em5'=>array(
                'link'=>'https://em5.beyondrm.com/XXXX/',
                'user_name'=>'XXXX',
                'secret_key'=>'XXXX',
                'doi_data_source'=>6,
                'soi_data_source'=>6
            ),
        ),
        'uk'=>array(
            'em5'=>array(
                'link'=>'https://em5.beyondrm.com/XXXX/',
                'user_name'=>'XXXX',
                'secret_key'=>'XXXX',
                'doi_data_source'=>3,
                'soi_data_source'=>3
            ),
            'em6'=>array(
                'link'=>'https://em6.beyondrm.com/XXXX/',
                'user_name'=>'XXXX',
                'secret_key'=>'XXXX',
                'doi_data_source'=>4,
                'soi_data_source'=>4
            ),
        )
    ),
    'test'=>array(), //Rohit=>Test Server Data not to send to Beyond Portal
    'development'=>array()
);

//Rohit=> Whitelist IP's from which Beyond Request will be processed (like Unsubscribed)
$config['beyond_whitelist_ips']=array(
    'germany'=>array('182.48.243.176','123.252.183.73', '148.251.129.135','148.251.245.12','46.4.90.229'),
    'uk'=>array('182.48.243.176','123.252.183.73', '148.251.129.135','148.251.245.12','46.4.90.229'),
    'australia'=>array('182.48.243.176','123.252.183.73', '148.251.129.135','148.251.245.12','46.4.90.229')
);

//Rohit=> Countries for Which SOI Unsubscribed to be done on DOI
$config['allowed_country_beyond_soi_unsubscribed']=array(
    'germany'=>true,
    'uk'=>false,
    'australia'=>false
);
