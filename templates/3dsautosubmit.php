<html>
    <head></head>
    <body onload="<?php if ($var['is_form_visible']): ?>parent.PAAYShow3DSFrame();<?php endif; ?> document.form3ds.submit()">
        <form name="form3ds" action="<?php echo $var['AcsUrl']; ?>" method="post">
            <input name="PaReq" type="hidden" value="<?php echo $var['PaReq']; ?>">
            <input name="MD" type="hidden" value="<?php echo $var['MD']; ?>">
            <input name="TermUrl" type="hidden" value="<?php echo $var['TermUrl']; ?>">
        </form>
    </body>
</html>
