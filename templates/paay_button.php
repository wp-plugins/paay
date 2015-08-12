<?php if($var['visible']){ ?>
    <div class="paay-button-placeholder"></div>
<?php } ?>

<script type="text/javascript">
    window.addEventListener('load', function() {
        var settings = {};

        window.paayWoo = new PAAY(
            '/?page=paay_handler&paay-module=createTransaction',
            '/?page=paay_handler&paay-module=cancelTransaction',
            '/?page=paay_handler&paay-module=awaitingApproval',
            '/?page=paay_handler&paay-module=approveWithout3ds',
            settings
        );
        paayWoo.loadButtons();
        paayWoo.bindEvents();
    });
</script>