<?php
/** Native Gutenberg save markup; no database or filesystem writes. */
function sy3_case_gallery_image(int $id, string $url, string $alt, string $caption): string {
    $attrs = ['id'=>$id, 'sizeSlug'=>'full', 'linkDestination'=>'none', 'lightbox'=>['enabled'=>true]];
    // WordPress supplies dimensions, srcset and lazy loading at render time.
    return '<!-- wp:image '.wp_json_encode($attrs, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).' --><figure class="wp-block-image size-full"><img src="'.esc_url($url).'" alt="'.esc_attr($alt).'" class="wp-image-'.$id.'"/>'.($caption!==''?'<figcaption class="wp-element-caption">'.esc_html($caption).'</figcaption>':'').'</figure><!-- /wp:image -->' . "\n";
}

function sy3_case_gallery_block(array $images): string {
    $count = count($images);
    if ($count < 1 || $count > 3) throw new RuntimeException('One to three gallery images required.');
    return '<!-- wp:group {"className":"sy3-case-gallery count-'.$count.'"} --><div class="wp-block-group sy3-case-gallery count-'.$count.'"><!-- wp:heading {"level":3} --><h3 class="wp-block-heading">Проект в деталях</h3><!-- /wp:heading --><!-- wp:gallery {"columns":'.$count.',"imageCrop":false,"linkTo":"none","sizeSlug":"full"} --><figure class="wp-block-gallery has-nested-images columns-'.$count.'">'.implode('', $images).'</figure><!-- /wp:gallery --></div><!-- /wp:group -->' . "\n";
}

function sy3_case_gallery_insert(string $body, string $gallery): string {
    $position = strpos($body, '<!-- wp:heading');
    if (preg_match('~<!-- /wp:paragraph -->\s*~', $body, $m, PREG_OFFSET_CAPTURE)) {
        $position = $m[0][1] + strlen($m[0][0]);
    }
    if ($position === false) $position = 0;
    return substr($body, 0, $position).$gallery.substr($body, $position);
}
