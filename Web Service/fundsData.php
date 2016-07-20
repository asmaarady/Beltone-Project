<?php
    include('../config.php');

	include(MODULES.'database.module.php');  
    include(MODULES.'nusoap/nusoap.php');

	//date_default_timezone_set(GMT_PLUS == 2 ? 'Africa/Johannesburg' : 'Africa/Cairo');
	
	write_fund_log('Starting update of funds data..');	
	
UpdateFunds();
	
function GetFundID($portfolioCode)
{
	$strQuery = "select ID from Funds where FundCode = '".$portfolioCode."' and IsActive = 1";
	
	$result = query($strQuery);
	
	$row = mysql_fetch_assoc($result);
	

	if(!empty($row))
	{

		return $row['ID'];		
	}
	else
		return null;
}
	
function UpdateFund($fundID, $fundValues)
{
	$lastUpdateResult = query("select FundLastUpdate from Funds_Data where FundID = ".$fundID);
	$oldRow = mysql_fetch_assoc($lastUpdateResult);		
	$oldLastUpdate = date('Y-m-d', strtotime($oldRow['FundLastUpdate']));		
	
	if(empty($oldRow))//insert new record
	{
		write_fund_log('Inserting fund '.$fundID.' data');
		
		$strQuery = "insert into Funds_Data(FundID,".implode(',',array_keys($fundValues)).",ModifyDate) values(".$fundID.",'".implode('\',\'',array_values($fundValues))."',now())";
		query($strQuery);
		
		write_fund_log('Fund '.$fundID.' inserted');
	}
	else//update current record
	{
		
		$newLastUpdate = $fundValues['FundLastUpdate'];
		
		if($oldLastUpdate != $newLastUpdate)
		{
			write_fund_log('Updating fund '.$fundCode.';Old Update Time: '.$oldLastUpdate.', New Update Time'.$newLastUpdate);
			
			$sqlParams = '';
			
			foreach($fundValues as $name=>$value)
			{	
				$sqlParams .= $name."='".$value."',";
			}
			
			$sqlParams .= "ModifyDate = now()";
			
			$strQuery = "update Funds_Data set ".$sqlParams." where FundID = ".$fundID;			
			
			query($strQuery);
			
			write_fund_log('Fund '.$fundID.' updated');
		}
		else
			write_fund_log('Fund '.$fundID.' is already up to date; '.$oldLastUpdate.','.$newLastUpdate);		
		
	}
	
	
}
	
function UpdateFunds()
{
	$soapclient = new nusoap_client(Funds_WEBSERVICE_URL, true);
	$soapclient->soap_defencoding='utf-8';
	$soapclient->setEndpoint(Funds_WEBSERVICE_URL);	
	
	write_fund_log('Starting funds update...');
	write_fund_log('Calling GetFundsData...');	
	
	$params['anything'] = 'nothing';
	
	$nresult = $soapclient->call('GetFundsData',$params);
	
	$err =  $soapclient->getError();
	
    if(!empty($err)) 
	{
		write_fund_log(implode(',',$err));
	}
	else{ //successfully returned; check data returned
	
		foreach($nresult['GetFundsDataResult']['ArrayOfKeyValueOfstringstring'] as $rowindex=>$row){			
		
		$fundCode = '';
		$fundValues = array();
					
		foreach($row['KeyValueOfstringstring'] as $values){
	
		switch($values['Key']){
			case 'NAVUnit':
				$fundValues['FundNav'] = $values['Value'];				
				break;
			case 'MTDRtrn':
				$fundValues['FundMTD'] = $values['Value'];
				break;
			case 'YTDRtrn':
				$fundValues['FundYTD'] = $values['Value'];
				break;
			case 'ITDRtrn':
				$fundValues['FundITD'] = $values['Value'];
				break;
			case 'InDate':
				$fundValues['FundInceptionDate'] = date('Y-m-d', strtotime($values['Value']));
				break;
			case 'RepDate':
				$lastUpdate = $values['Value'];				
				$fundValues['FundLastUpdate'] = date('Y-m-d', strtotime($lastUpdate));
				break;
				
			case 'Portf':				
				$fundCode = $values['Value'];
				break;
				}
			}
			
			if(empty($fundCode))//fund code could not be extracted
				continue;

		$fundID = GetFundID($fundCode);

		if(empty($fundID))//this fund is not active on the website so, ignore it
			continue;
		
		UpdateFund($fundID,$fundValues);	
		
		}
	}
}

function write_fund_log($string) {

$string = date('Y-m-d H:i:s') . ' ' . trim($string) . "\n";

if(!(php_sapi_name() === 'cli'))
	$string .= '<br/>';

echo $string;
		
	if(is_writeable(ENTREPOTROOT)) {	

		$filename = 'fund_log_' . date('Y-m-d') . '.log';

		if(!file_exists(ENTREPOTROOT . $filename) || filesize(ENTREPOTROOT . $filename) < 2000000000) {
			$fp = fopen(ENTREPOTROOT . $filename, 'a');
			fwrite($fp, $string);
			fclose($fp);
		}
	}
}
?>
