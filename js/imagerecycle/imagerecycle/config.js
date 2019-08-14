jQuery.noConflict();
jQuery(document).ready(function($) {

    //Do not load anything if not required
    if(typeof(page)==='undefined' || page!=='config'){
        return;
    }

    var sdir = '/';
    jSelectFolders = function() {
        curFolders.sort();
        window.parent.document.getElementById('include_folders').value =  curFolders;
        if(curFolders.length && curFolders[0]=='')
        {
            curdata = window.parent.document.getElementById('include_folders').value;
            window.parent.document.getElementById('include_folders').value = curdata.substr(1, curdata.length);
        }

    };
    $('#wpio_foldertree').wpio_jaofoldertree({
        script  : getFolders_url,
        usecheckboxes : true,
        showroot : '/',
        oncheck: function(elem,checked,type,file){
            var dir = file;
            if(file.substring(file.length-1) ==  sdir) {
                file = file.substring(0,file.length-1);
            }
            if(file.substring(0,1) ==  sdir) {
                file = file.substring(1,file.length);
            }
            if(checked ) {
                if(file!="" && curFolders.indexOf(file)== -1) {
                    curFolders.push(file);
                }
            } else {

                if(file != "" && !$(elem).next().hasClass('pchecked') ) {
                    temp = [];
                    for(i=0;i<curFolders.length;i++) {
                        curDir = curFolders[i];
                        if(curDir.indexOf(file)!==0) {
                            temp.push(curDir);
                        }
                    }
                    curFolders = temp;
                } else {
                    var index  = curFolders.indexOf(file);
                    if(index>-1) {
                        curFolders.splice(index,1);
                    }
                }
            }

        }
    });

    jQuery('#wpio_foldertree').bind('afteropen',function(){

        jQuery(jQuery('#wpio_foldertree').wpio_jaofoldertree('getchecked')).each(function() {
            curDir = this.file;
            if(curDir.substring(curDir.length-1) ==  sdir) {
                curDir = curDir.substring(0,curDir.length-1);
            }
            if(curDir.substring(0,1) ==  sdir) {
                curDir = curDir.substring(1,curDir.length);
            }
            if(curFolders.indexOf(curDir)== -1) {
                curFolders.push(curDir);
            }
        });
        spanCheckInit();
    });

    spanCheckInit = function() {
        $("span.check").unbind('click');
        $("span.check").bind('click', function() {
            $(this).removeClass('pchecked');

            $(this).toggleClass('checked');
            if($(this).hasClass('checked')) {
                $(this).prev().prop('checked', true).trigger('change');
            }else {
                $(this).prev().prop('checked', false).trigger('change');
            }
            setParentState(this);
            setChildrenState(this);

        });

    };

    setParentState = function(obj) {
        var liObj = $(obj).parent().parent(); //ul.jaofoldertree
        var noCheck = 0, noUncheck =0, totalEl = 0;
        liObj.find('li span.check').each(function(){

            if($(this).hasClass('checked')) {
                noCheck++;
            }else {
                noUncheck++;
            }
            totalEl++;
        });

        if(totalEl==noCheck) {
            liObj.parent().children('span.check').removeClass('pchecked').addClass('checked');
            liObj.parent().children('input[type="checkbox"]').prop('checked',true).trigger('change');
        }else if(totalEl==noUncheck) {
            liObj.parent().children('span.check').removeClass('pchecked').removeClass('checked');
            liObj.parent().children('input[type="checkbox"]').prop('checked',false).trigger('change');
        }else {
            liObj.parent().children('span.check').removeClass('checked').addClass('pchecked');
            liObj.parent().children('input[type="checkbox"]').prop('checked',false).trigger('change');
        }

        if(liObj.parent().children('span.check').length>0) {
            setParentState(liObj.parent().children('span.check'));
        }
    };

    setChildrenState = function(obj) {
        if($(obj).hasClass('checked')) {
            $(obj).parent().find('li span.check').removeClass('pchecked').addClass("checked");
            $(obj).parent().find('li input[type="checkbox"]').prop('checked',true).trigger('change');
        }else {
            $(obj).parent().find('li span.check').removeClass("checked");
            $(obj).parent().find('li input[type="checkbox"]').prop('checked',false).trigger('change');
        }
    };

    //set the dfault cron url and periodictiy

    if((is_compress_auto == '1') || (is_compress_auto == '2'))
    {
        addremoveautocomp(is_compress_auto);
    }


    function addremoveautocomp(selected)
    {
        var period_data = ['5mins','10mins','20mins', '30min','1h','2h','6h','12h','24h','48h'];
        var current_period ='';
        comp_auto =  $('#compress_auto').val();
        if((comp_auto == '2') || (comp_auto == '1'))  {
            $('.remove_tag').remove();

            html_data = '';
            if(selected=='2'){
                html_data +='<tr class = "remove_tag"><th scope="row"> Cron URL</th><td colspan="3">';
                html_data += '<input id="cron_urltxt" class="formData" name="_mageio_settings[resize_image]" readonly= "readonly" type="text" value="' + cron_url+'" size="10"/></td></tr>';
            }
            html_data +='<tr class = "remove_tag"><th class = "remove_tag" scope="row" >Cron Period</th><td colspan="2">';
            html_data +='<select id = "cron_periodicity" name="_mageio_settings[period_setting]">';

            for(i = 0; i < period_data.length; i++){
                html_data +='<option value="' + period_data[i];
                if(cron_periodicity == period_data[i]){
                    html_data +=  '"  selected>' + period_data[i] +'</option>';
                }
                else{
                    html_data +=  '">' + period_data[i] +'</option>';
                }
            }
            html_data +='</select></td></tr>';
            $('#compress_auto_setting').after(html_data);
        }
        else{
            //remove the data
            $('.remove_tag').remove();

        }
    }

    $('#compress_auto').change(function(){
        addremoveautocomp($(this).val());
    });


    $('.do-index').bind('click', function (e) {
        e.preventDefault();
        if(!confirm("Depending on the numbe of images you have, this can take some time to reindex all the images on your server.")){
            return;
        }

        $.ajax({
            url: reindex_init_url,
            data: {form_key: window.FORM_KEY},
            type: 'post',
            dataType: 'json',
            success: function (response) {
                if (response.status === true) {
                    window.location.reload();
                }
            }
        });
    });
    
});