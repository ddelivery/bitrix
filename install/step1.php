<?if(!check_bitrix_sessid()) return;
if($message!==false):
    echo CAdminMessage::ShowMessage(array('MESSAGE' => $message, 'TYPE' => $type));
endif;
?>
<form action="<?echo $APPLICATION->GetCurPage()?>">
    <input type="hidden" name="lang" value="<?echo LANGUAGE_ID; ?>">
    <input type="submit" name="" value="<?echo GetMessage("MOD_BACK")?>">
<form>