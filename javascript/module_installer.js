jQuery(function() {
  jQuery.getJSON('?q=module_installer/list',function(json) {
    document._moduleList = json;
    jQuery('input#module-intaller-addMoreButton').removeAttr('disabled').attr('value','+ Add More');
    jQuery("input.module_name").autocomplete(json).result(function(event, item) {
      var obj = jQuery(this).parent().parent();
      jQuery("iframe#module_installer-drupalPage").attr("src","http://www.drupal.org/project/"+item);
      jQuery(obj).find('td.version-td > div.ahah-progress-throbber').addClass('ahah-progress').find('div.throbber').html("&nbsp;");
      jQuery.getJSON('?q=module_installer/module/'+item,function(json) {
        jQuery(obj).find('td.version-td > div.ahah-progress-throbber').removeClass('ahah-progress').find('div.throbber').html(json.data);
        if(json.status) {
          jQuery(obj).find('input.download').removeAttr('disabled');
        }
      })
    });

    jQuery('input.module_name').livequery('focus',function () {
      if(jQuery(this).val() == "Type Module Name") {
        $(this).val("");
      }
    }).livequery('blur',function () {
      var tr = jQuery(this).parent().parent();
      if(!jQuery(this).val().length) {
        $(this).val("Type Module Name");
        jQuery(tr).find('td.version-td > div.ahah-progress-throbber > div.throbber').html("Please select Module first.");
        jQuery(tr).find('input.download').attr('disabled','disabled');
      }
    });

    jQuery('input#module-intaller-addMoreButton').click(function () {
      var evenOrOdd = "odd";
      if(typeof document._moduleList == 'object') {
        if($("table#module-installer-table tr:last").hasClass('odd')) {
          evenOrOdd = "even";
        }
        jQuery('<tr class="'+evenOrOdd+'">'
          +'<td><input type="text" value="Type Module Name" class="module_name ac_input" name="module_name" autocomplete="off"></td>'
          +'<td class="version-td"><div class="ahah-progress-throbber"><div class="throbber">Please select Module first.</div></div><div class="content"></div></td>'
          +'<td class="module_installer-moduleDownload"><input type="button" disabled="disabled" value="Download" class="download" name="download"></td>'
          +'</tr>'
          ).appendTo('table#module-installer-table').find("input.module_name").autocomplete(json).result(function(event, item) {
          var obj = jQuery(this).parent().parent();
          jQuery("iframe#module_installer-drupalPage").attr("src","http://www.drupal.org/project/"+item);
          jQuery(obj).find('td.version-td > div.ahah-progress-throbber').addClass('ahah-progress');
          jQuery.getJSON('?q=module_installer/module/'+item,function(json) {
            jQuery(obj).find('td.version-td > div.ahah-progress-throbber').removeClass('ahah-progress').find('div.throbber').html(json.data);
            if(json.status) {
              jQuery(obj).find('input.download').removeAttr('disabled');
            }
          });
        });
      }
    });

    jQuery("table#module-installer-table td.module_installer-moduleDownload > input.download").livequery('click',function() {
      var td = jQuery(this).parent();
      var tr = jQuery(this).parent().parent();
      var id = "download-"+new Date().getTime();
      var module_name = jQuery(tr).find('input.module_name').val();
      var version = jQuery(tr).find('select.module_installer-ModuleVersion').val();
      jQuery(this).attr({'disabled':'disabled','value':'Downloading...'})
      jQuery(td).attr("id",id);
      jQuery.getScript("?q=module_installer/download/"+module_name+"/"+version+"/"+id);
    });
  });
});