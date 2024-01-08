<div class="control-group">
    <label for="allow_once" class="control-label cm-required">{__("allow_once_label")}</label>
    <select id="allow_once" name="product_data[allow_once]">
        <option value="1" {if $product_data.allow_once === "1"}selected{/if}>{__("all_actions")}</option>
        <option value="2" {if $product_data.allow_once === "2"}selected{/if}>{__("prohibit_all")}</option>
        <option value="3" {if $product_data.allow_once === "3"}selected{/if}>{__("just_add_to_wishlist")}</option>
    </select>
</div>