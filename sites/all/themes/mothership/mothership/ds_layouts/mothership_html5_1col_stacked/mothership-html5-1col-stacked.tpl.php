<?php
//kpr(get_defined_vars());
?>
<!-- html5 1 col stacked -->
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

  <?php if ($main_nowrapper): ?>
      <?php print $main_nowrapper; ?>  
  <?php endif; ?>
  
  <?php if ($main): ?>
    <div <?php if($main_classes){ ?>class="<?php print $main_classes; ?>"<?php } ?>>
      <?php print $main; ?>
    <?php if($main_classes){ ?></div><?php } ?>
    </div>
  <?php endif; ?>


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
