<iframe
    <?php if ('always' !== $var['is_visible']): ?>
        style="display: none;"
    <?php endif; ?>
    <?php if ('never' !== $var['is_visible']): ?>
        onload="window.PAAYHandleIframes();"
    <?php endif; ?>
    src="/?paay-module=paay-3ds-form&order=<?php echo $var['order_id']; ?>"
    class="paay-3ds-iframe"
    frameborder="0"
    width="100%"
>
</iframe>