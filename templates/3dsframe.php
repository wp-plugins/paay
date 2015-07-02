<?php echo paay_checkout(); ?>
<iframe
    <?php if ('always' !== $var['is_visible']): ?>
        style="display: none;"
    <?php endif; ?>
    <?php if ('never' !== $var['is_visible']): ?>
        onload="window.PAAYHandleIframes();"
    <?php endif; ?>
    <?php if ('never' === $var['is_visible']): ?>
        onload="window.PAAYHandleNeverIframes();"
    <?php endif; ?>
    src="/?paay-module=paay-3ds-form&order=<?php echo $var['order_id']; ?>"
    class="paay-3ds-iframe"
    frameborder="0"
    width="100%"
    data-order_id="<?php echo $var['order_id']; ?>"
>
</iframe>