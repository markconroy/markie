<div id="cccwr">
    <div id="ccc-state" class="ccc-pause">
        <div id="ccc-icon">
            <button>
                <span><?php print t('Cookie Control');?></span>
            </button>
        </div>
        <div class="ccc-widget">
            <div class="ccc-outer">
                <div class="ccc-inner">
                    <h2><?php print t('Cookie Control');?></h2>
                    <div class="ccc-content">
                        <p class="ccc-intro"></p>
                        <div class="ccc-expanded"></div>

                        <div id="ccc-cookies-switch" style="background-position-x: 0;">
                          <a id="cctoggle" href="#" style="background-position-x: 0;" name="cctoggle"><span id="cctoggle-text">Cookies test</span></a>
                        </div>
          
                        <div id="ccc-implicit-warning">
                          <?php print t('(One cookie will be set to store your preference)'); ?>
                        </div>
          
                        <div id="ccc-explicit-checkbox">
                          <label><input id="cchide-popup" type="checkbox" name="ccc-hide-popup" value="Y" /> <?php print t('Do not ask me again'); ?><br /></label> <?php print t('(Ticking this sets a cookie to hide this popup if you then hit close. This will not store any personal information)'); ?>
                        </div>
              
                        <p class="ccc-about">
                            <small>
                                <a href="http://www.civicuk.com/cookie-law" target="_blank"><?php print t('About this tool');?></a>
                            </small>
                        </p>
                        <a class="ccc-icon" href="http://www.civicuk.com/cookie-law" target="_blank"
                        title="<?php print t('About Cookie Control');?>"><?php print t('About Cookie Control');?></a>
                        <button class="ccc-close"><?php print t('Close');?></button>
                    </div>
                </div>
            </div>
            <button class="ccc-expand"><?php print t('read more');?></button>
        </div>
    </div>
</div>