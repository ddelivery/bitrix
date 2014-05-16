<?if(!check_bitrix_sessid()) return;
if($message!==false):
    echo CAdminMessage::ShowMessage(array('MESSAGE' => $message, 'TYPE' => $type));
endif;
if($installOk) {
    echo BeginNote();
    echo GetMessage("DIGITAL_DELIVERY_MOD_INST_OK");
    echo EndNote();
}
?>
<form action="<?echo $APPLICATION->GetCurPage()?>">
    <input type="hidden" name="lang" value="<?echo LANGUAGE_ID; ?>">
    <input type="submit" name="" value="<?echo GetMessage("MOD_BACK")?>">
<form>