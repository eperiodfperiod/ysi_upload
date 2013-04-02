<?php
// Form Validation
$form_key_check = md5($_POST['form_first_name'].$_POST['form_last_name'].$_POST['form_email']);
if ($form_key_check != $_POST['form_key']){
	echo json_encode(array("error" => "Invalid form key. Please try again later."));
	return false;
}

$curl = curl_init();
$endpoint = "https://www.yousendit.com/uploadContainer?phi_action=app/initUploadFeature";
curl_setopt($curl, CURLOPT_URL, $endpoint);
curl_setopt($curl, CURLOPT_VERBOSE, 0);
curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
curl_setopt($curl, CURLOPT_POST, 1);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($curl, CURLOPT_HTTPHEADER, array('Accept: application/xml','Content-Length: '. $data_len));
$dropbox_name = "your_ysi_dropbox_name";
$sendername = $_POST['form_first_name'].$_POST['form_last_name'];
$senderemail = $_POST['form_email'];
$subject = "File(s) Received";
$message = $_POST['form_message'];
$form_files = array(/* List of $_FILES array members to process */);
$fcount = 0;
foreach ($form_files as $file){
	if (!empty($file)){
		$upload_tmp_filename[$fcount] = $file['tmp_name'];
		$upload_filename[$fcount] = $file['name'];
		if (!empty($upload_tmp_filename[$fcount]) && !empty($upload_filename[$fcount])){
			$file_moved = move_uploaded_file($upload_tmp_filename[$fcount], "/tmp/" . $upload_filename[$fcount]);
			if (!$file_moved){
				echo json_encode(array("error" => "File storage error. Please try again later."));
				return false;
			}
			$fcount++;
		}
	}
}

$data = array('dropbox'=>$dropbox_name,'subject'=>$subject,'description'=>$message,'fcount'=>$fcount,'from'=>$senderemail,'name'=>$sendername);
curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
$init_vals = curl_exec($curl);
curl_close($curl);

$init_vals = json_decode($init_vals);

if ($init_vals->status == "valid") {
	for($i=0;$i<$fcount;$i++){
		$ftf_url[$i] = $init_vals->ftf_url[$i];
	}
	$batch_id = $init_vals->batch_id;
}
else {
	echo json_encode(array("error" => "Initialization error. Please try again later."));
	return false;
}

$file_success = true;

for($i=0;$i<$fcount;$i++){
	$curl_upload = curl_init();
	$endpoint = $ftf_url[$i];
	curl_setopt($curl_upload, CURLOPT_URL, $endpoint);
	curl_setopt($curl_upload, CURLOPT_VERBOSE, 0);
	curl_setopt($curl_upload, CURLOPT_SSL_VERIFYPEER, 0);
	curl_setopt($curl_upload, CURLOPT_POST, 1);
	curl_setopt($curl_upload, CURLOPT_RETURNTRANSFER, 1);
	$fnames[$i]='@/tmp/'.$upload_filename[$i];
	$types[$i]='application/octet-stream';
	$data=array('rcpt'=>'','bid'=>$batch_id,'pdf_mark'=>'false','fname'=>$fnames[$i],'type'=>$types[$i]);
	curl_setopt($curl_upload, CURLOPT_POSTFIELDS, $data);
	$upload_vals = curl_exec($curl_upload);
	curl_close($curl_upload);
	$file_info = new SimpleXMLElement($upload_vals);
	if ($file_info->{'upload-status'} != "successful"){
		$file_success = false;
	}
}

if ($file_success){
	$curl_upload = curl_init();
	$endpoint = "https://www.yousendit.com/uploadContainer?phi_action=app/endFeatureUpload";
	curl_setopt($curl_upload, CURLOPT_URL, $endpoint);
	curl_setopt($curl_upload, CURLOPT_VERBOSE, 0);
	curl_setopt($curl_upload, CURLOPT_SSL_VERIFYPEER, 0);
	curl_setopt($curl_upload, CURLOPT_POST, 1);
	curl_setopt($curl_upload, CURLOPT_RETURNTRANSFER, 1);
	$data = array('batchId'=>$batch_id,'dropbox'=>$dropbox_name,'from'=>$senderemail,'name'=>$sendername);
	curl_setopt($curl_upload, CURLOPT_POSTFIELDS, $data);
	$upload_vals = curl_exec($curl_upload);
	curl_close($curl_upload);
	$success_msg = "Upload successful! We'll be in touch soon.";
	echo json_encode(array("status" => $success_msg));
}

else {
	echo json_encode(array("error" => "Upload error. Please try again later."));
	return false;
}

?>

