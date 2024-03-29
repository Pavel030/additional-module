{if $payment_method.processor_params.payment_system == 'post'}
    <div class="control-group">
        <label class="cm-required" for="mailofrussia_sender_index">{__("text_payanyway_mailofrussiasenderindex")}</label>
        <input class="input-text cm-autocomplete-off" type="text" id="mailofrussia_sender_index" name="payment_info[mailofrussiaSenderIndex]" value="" size="35" />
    </div>
    <div class="control-group">
        <label class="cm-required" for="mailofrussia_sender_address">{__("text_payanyway_mailofrussiasenderaddress")}</label>
        <input class="input-text cm-autocomplete-off" type="text" id="mailofrussia_sender_address" name="payment_info[mailofrussiaSenderAddress]" value="" size="35" />
    </div>
    <div class="control-group">
        <label class="cm-required" for="mailofrussia_sender_name">{__("text_payanyway_mailofrussiasendername")}</label>
        <input class="input-text cm-autocomplete-off" type="text" id="mailofrussia_sender_name" name="payment_info[mailofrussiaSenderName]" value="" size="35" />
    </div>
{elseif $payment_method.processor_params.payment_system == 'moneymail'}
    <div class="control-group">
        <label class="cm-required cm-email" for="buyer_email">{__("text_payanyway_buyeremail")}</label>
        <input class="input-text" type="text" id="buyer_email" name="payment_info[buyerEmail]" value="" size="35" />
    </div>
{elseif $payment_method.processor_params.payment_system == 'euroset'}
    {include file="components/phone.tpl"
        id="rapida_phone"
        name="payment_info[rapidaPhone]"
        value=""
        required=true
        class="cm-autocomplete-off"
        label_text=__("text_payanyway_rapidaphone")
        width="full"
    }
    <script>
        //<![CDATA[
        (function(_, $) {
            $(document).ready(function() {
                $.ceFormValidator('setRegexp', {
                    rapida_phone: {
                        regexp: {literal}"^(\\+[0-9]{10,20})$"{/literal},
                        message: "{__("text_payanyway_error_rapida_phone")|escape:javascript}"
                    }
                });
            });
        }(Tygh, Tygh.$));
        //]]>
    </script>
{elseif $payment_method.processor_params.payment_system == 'webmoney'}
    <div class="control-group">
        <label class="cm-required" for="account_id">{__("text_payanyway_webmoneyaccountid")}</label>
        <select id="account_id" name="payment_info[accountId]">
            <option value="2">WMR</option>
            <option value="3">WMZ</option>
            <option value="4">WME</option>
        </select>
    </div>
{/if}
