{*<!--
/*********************************************************************************
  ** The contents of this file are subject to the vtiger CRM Public License Version 1.0
   * ("License"); You may not use this file except in compliance with the License
   * The Original Code is:  vtiger CRM Open Source
   * The Initial Developer of the Original Code is vtiger.
   * Portions created by vtiger are Copyright (C) vtiger.
   * All Rights Reserved.
  *
 ********************************************************************************/
-->*}
<!-- BEGIN: main -->
<form name="SendPDFMail">
<div id="roleLay2" style="z-index:12;display:block;width:350px;" class="crmvDiv">
	<div class="closebutton" onclick="fninvsh('roleLay2');"></div>
	<table border=0 cellspacing=0 cellpadding=10 width=100% class="level3Bg">
		<tr>
			<td width="100%" id="sendpdfmail_cont_handle" style="cursor:move; font-weight:bold;">{$MOD.SELECT_EMAIL}
				{if $ONE_RECORD neq 'true'}
					({$MOD.LBL_MULTIPLE} {$APP[$FROM_MODULE]})
				{/if}
				&nbsp;
			</td>
		</tr>
	</table>
	<table border=0 cellspacing=0 cellpadding=5 width=95% align=center> 
		<tr><td class="small">
			<table border=0 cellspacing=0 cellpadding=5 width=100% align=center>
				<tr>
					<td align="left">
					{if $ONE_RECORD eq 'true'}
						<b>{$ENTITY_NAME}</b> {$MOD.LBL_MAILSELECT_INFO}.<br><br>
					{else}
						{$MOD.LBL_MAILSELECT_INFO1} {$APP[$FROM_MODULE]}.{$MOD.LBL_MAILSELECT_INFO2}<br><br>
					{/if}
						<div style="height:120px;overflow-y:auto;overflow-x:hidden;" align="center">
							<table border="0" cellpadding="5" cellspacing="0" width="90%">
								{foreach name=emailids key=fieldid item=elements from=$MAILINFO}
								<tr>
									{if $smarty.foreach.emailids.iteration eq 1}	
									    <td align="center"><input type="checkbox" checked value="{$fieldid}" name="semail" /></td>
									{else}
								    	<td align="center"><input type="checkbox" value="{$fieldid}" name="semail"  /></td>
									{/if}
									{if $PERMIT eq '0'}
    									{if $ONE_RECORD eq 'true'}	
    									    <td align="left"><b>{$elements.0}</b><br>{$MAILDATA[$smarty.foreach.emailids.index]}</td>
    									{else}
    								    	<td align="left"><b>{$elements.0}</b></td>
    									{/if}
									{else}
                                        <td align="left"><b>{$elements.0}</b><br>{$MAILDATA[$smarty.foreach.emailids.index]}</td>
                                    {/if}
								</tr>
								{/foreach}
							</table>
						</div>
					</td>	
				</tr>
			</table>
		</td></tr>
	</table>
	<table border=0 cellspacing=0 cellpadding=5 width=100% class="layerPopupTransport">
		<tr><td align=center class="small">
			<input type="button" name="{$APP.LBL_SELECT_BUTTON_LABEL}" value=" {$APP.LBL_SELECT_BUTTON_LABEL} " class="crmbutton small create" onClick="validate_sendPDFmail('{$IDLIST}','{$FROM_MODULE}');"/>&nbsp;&nbsp;
			<input type="button" name="{$APP.LBL_CANCEL_BUTTON_LABEL}" value=" {$APP.LBL_CANCEL_BUTTON_LABEL} " class="crmbutton small cancel" onclick="fninvsh('roleLay2');" />
		</td></tr>
	</table>
</div>
</form>