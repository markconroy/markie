<?php
//kpr(get_defined_vars());
?>
<article <?php if($classes){ ?>class="<?php print $classes;?>"<?php } ?> role="article">
  <?php
    if (isset($title_suffix['contextual_links'])){
       print render($title_suffix['contextual_links']);
   }
  ?>

  <?php if ($header OR $hgroup): ?>
    <header <?php if($header_classes){ ?>class="<?php print $header_classes; ?>"<?php } ?>>
    <?php print $header; ?>
      <?php if($hgroup){ ?>
        <hgroup <?php if($hgroup_classes){ ?>class="<?php print $hgroup_classes; ?>"<?php } ?>>
        <?php print $hgroup; ?>
        </hgroup>
      <?php } ?>
    </header>
  <?php endif; ?>

  <?php if ($top): ?>
    <?php if($top_classes){ ?><div class="<?php print $top_classes; ?>"><?php } ?>
      <?php print $top; ?>
    <?php if($top_classes){ ?></div><?php } ?>
  <?php endif; ?>

  <div class="content">
    <?php if ($left): ?>
      <div <?php if($left_classes){ ?>class="<?php print $left_classes; ?>"<?php } ?>>
        <?php print $left; ?>
      </div>
    <?php endif; ?>

    <?php if ($middle): ?>
      <div <?php if($middle_classes){ ?>class="<?php print $middle_classes; ?>"<?php } ?>>
        <?php print $middle; ?>
      </div>
    <?php endif; ?>

    <?php if ($right): ?>
      <div <?php if($right_classes){ ?>class="<?php print $right_classes; ?>"<?php } ?>>
        <?php print $right; ?>
      </div>
    <?php endif; ?>
  </div>

  <?php if ($bottom): ?>
    <div <?php if($bottom_classes){ ?>class="<?php print $bottom_classes; ?>"<?php } ?>>
      <?php print $bottom; ?>
    </div>
  <?php endif; ?>

  <?php if ($footer): ?>
    <footer <?php if($footer_classes){ ?>class="<?php print $footer_classes; ?>"<?php } ?>>
      <?php print $footer; ?>
    </footer>
  <?php endif; ?>
</article>


