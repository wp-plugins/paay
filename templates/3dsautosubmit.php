<html>
    <head></head>
    <body onload="document.form3ds.submit()">
        <form name="form3ds" action="<?php echo $var['AcsUrl']; ?>" method="post">
            <input name="PaReq" type="hidden" value="<?php echo $var['PaReq']; ?>">
            <input name="MD" type="hidden" value="<?php echo $var['MD']; ?>">
            <input name="TermUrl" type="hidden" value="<?php echo $var['TermUrl']; ?>">
        </form>
    </body>
</html>
