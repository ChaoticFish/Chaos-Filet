<?php

/**
 * @package     Chaos Filet
 * @subpackage  plg_content_chaosfilet
 * @version     1.3.0
 * @copyright   (C) 2026 Chaotic Fish Design
 * @license     GNU General Public License version 2 or later
 */

/**
 * Chaos Filet — "card" layout: {article 12|card}
 *
 * A Bootstrap 5 card built straight from $article, so the field list in the
 * tag is ignored. Also a working example of a custom layout: copy it to
 * templates/YOUR_TEMPLATE/html/plg_content_chaosfilet/card.php to change it,
 * or use it as the starting point for a layout of your own.
 *
 * See default.php for the full list of available variables.
 *
 * @var \stdClass $article
 * @var string    $wrapperClass
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<article class="card <?php echo $e($wrapperClass); ?>">
    <?php if ($article->image) : ?>
        <img class="card-img-top" src="<?php echo $e($article->image->src); ?>" alt="<?php echo $e($article->image->alt); ?>"<?php
            echo $article->image->width ? ' width="' . (int) $article->image->width . '" height="' . (int) $article->image->height . '"' : '';
        ?> loading="lazy" decoding="async">
    <?php endif; ?>
    <div class="card-body">
        <h3 class="card-title h5"><a href="<?php echo $e($article->url); ?>"><?php echo $e($article->title); ?></a></h3>
        <?php if ($article->date || $article->author) : ?>
            <p class="card-subtitle small text-body-secondary mb-2"><?php
                echo $article->date;
                echo $article->date && $article->author ? ' · ' : '';
                echo $e($article->author);
            ?></p>
        <?php endif; ?>
        <?php if ($article->excerpt !== '') : ?>
            <p class="card-text"><?php echo $e($article->excerpt); ?></p>
        <?php endif; ?>
        <a class="card-link" href="<?php echo $e($article->url); ?>"><?php echo $e(Text::_('PLG_CONTENT_CHAOSFILET_READMORE')); ?><span class="visually-hidden">: <?php echo $e($article->title); ?></span> <span aria-hidden="true">&rarr;</span></a>
    </div>
</article>
