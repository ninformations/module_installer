// $Id$
(function(element, size) {
  if(!isNaN(parseInt(jQuery(element).attr("bytes")))) {
    size += parseInt(jQuery(element).attr("bytes"));
  }
  jQuery(element).attr("bytes",size).html(size+" Bytes");
})("#<?php print $element; ?>",<?php print $size; ?>);