/* Image Recycle */
jQuery.noConflict();
jQuery(document).ready(function ($) {

    //Add event listener for simplified connect behaviour
    if(typeof(page)!=='undefined' && page==='connect'){
        window.addEventListener("message",
            function (e) {

                if (e.origin !== "https://www.imagerecycle.com") {
                    return;
                }
                var accountData = JSON.parse(e.data);

                jQuery.ajax({
                    url: autoaccount_url,
                    data: {form_key: window.FORM_KEY, apikey: accountData.key, secret: accountData.secret},
                    type: 'post',
                    dataType: 'json',
                    success: function (response) {
                        if (response.status == true) {
                            tb_remove();
                            window.location.reload();

                        }
                        else {
                            alert("Error, try again");
                        }
                    }
                });
            },
            false);
    }

    //Do not load anything if not required
    if(typeof(page)==='undefined' || page!=='main'){
        return;
    }
    
    var myStatus;

    if (optimizeall_flag == "On") {

        allcontroldisable();
        //disableButton();
        // button disable::  do-bulk-action
        myStatus = setInterval(setStatus, 10000);
    }
    switch ($('.error-no').val()) {
        case '0':
            $(".error").css("color", "red").html("Upload Success!");
            break;
        case '1':
            $(".error").css("color", "red").html("File not found!");
            break;
        case '2':
            $(".error").css("color", "red").html("Not yet set API and Seceret Key!");
            break;
        case '3':
            $(".error").css("color", "red").html("API and Seceret Key is not valid or Network error!");
            break;
        case '4':
            $(".error").css("color", "red").html("Can not get the file content!");
            break;
        case '5':
            $(".error").css("color", "red").html("Fail to file write error!");
            break;
        case '6':
            $(".error").css("color", "red").html("Extension error!");
            break;
    }
    setTimeout(function () {
            $.ajax({
                url: clearstatus_url,
                data: {form_key: window.FORM_KEY},
                type: 'post',
                dataType: 'json',
                success: function (response) {
                    if (response.status === true) {
                        $(".error").css("color", "red").html("");
                    }
                }
            })

        }, 5000
    );
    initButtons = function () {
        $('.optimize').unbind('click').bind('click', function (e) {

            e.preventDefault();
            var $this = $(e.target);
            var image = $this.data('image-realpath');

            if (!image || $this.hasClass('disabled')) {
                return false;
            }
            $.ajax({
                url: optimize_url,
                data: {image: image, form_key: window.FORM_KEY},
                type: 'post',
                dataType: 'json',
                beforeSend: function () {
                    $this.addClass('disabled');
                    disableButton();
                    var $status = $this.closest('tr').find('.ir-status');
                    $status.html($("#ir-setting-loader").html());
                },
                success: function (response) {

                    var $status = $this.closest('tr').find('.ir-status');
                    if (response.status === true) {
                        $status.addClass('msg-success');
                        $status.empty().text(response.datas.msg);
                        if (response.datas.newSize) {
                            $this.closest('tr').find('.filesize').empty().text(response.datas.newSize);
                        }
                        enableButton();
                        initButtons();
                        initOptimizeButton();
                    }
                    else {
                        if (response.datas.error) {
                            window.location.reload();
                        }

                        $status.addClass('msg-error');
                        $status.empty().text(response.datas.msg);
                        enableButton();
                        setTimeout(function () {
                            $this.removeClass('disabled');
                            $status.empty();
                        }, 5000);
                    }


                }
            });
        });

        $('.revert').unbind('click').bind('click', function (e) {
            e.preventDefault();
            var $this = $(e.target);
            var image = $this.data('image-realpath');
            if (!image || $this.hasClass('disabled')) {
                return false;
            }
            $.ajax({
                url: revert_url,
                data: {image: image, form_key: window.FORM_KEY},
                //async : false,
                type: 'post',
                dataType: 'json',
                beforeSend: function () {
                    $this.addClass('disabled');
                    //disableButton();
                    var $status = $this.closest('tr').find('.ir-status');
                    $status.html($("#ir-setting-loader").html());
                    $("button").addClass('disabled');
                },
                success: function (response) {

                    var $status = $this.closest('tr').find('.ir-status');
                    if (response.status === true) {
                        $status.addClass('msg-success');
                        $status.empty().text(response.datas.msg);
                        if (response.datas.newSize) {
                            $this.closest('tr').find('.filesize').empty().text(response.datas.newSize);
                        }
                        $this.removeClass('disabled revert').addClass('optimize').text('Optimize');
                        initButtons();
                        initOptimizeButton();
                    }
                    else {
                        $status.addClass('msg-error');
                        $status.empty().text(response.datas.msg);
                        setTimeout(function () {
                            $this.removeClass('disabled');
                            $('button').removeClass('disabled');
                            $status.empty();
                        }, 5000);
                    }

                }
            });
        })
    };

    initButtons();
    $('.ir-checkbox.check-all').bind('click', function (e) {
        var $status = $(this).is(':checked');
        $('.ir-checkbox').each(function (i, ck) {
            $(ck).prop('checked', $status);
        });
    });

    $('ul.pagination > li > a').bind('click', function (e) {
        e.preventDefault();
        var href = $(this).attr('href');
        window.location.href = href + '?' + $('#irForm').serialize();
    });

    var flag = 0;
    $('.do-bulk-action').bind('click', function (e) {
        e.preventDefault();
        setTimeout(function () {
            if (flag) return;
            if ($('.ir-bulk-action').val() == 'optimize_selected') {

                if ($('.ir-checkbox:checked').length < 1) {
                    alert("No image selected for optimize");
                } else {

                    $('.ir-checkbox:checked').each(function (i) {
                        $(this).parents('tr').find('.optimize').click();
                    });
                }
            } else {
                if ($('.ir-checkbox:checked').length < 1) {
                    alert("No image selected for revert");
                } else {
                    $('.ir-checkbox:checked').each(function (i) {
                        $(this).parents('tr').find('.revert').click();
                    });
                }
            }
        }, 250);
    });

    //Auto reload page with selected filters 
    $('#filter_type, #filter_status').change(function () {
        $('.do-search').click();
    });

    $('.do-search').bind('click', function (e) {
        e.preventDefault();
        var data = {
            form_key: window.FORM_KEY,
            filter_type: $('#filter_type').val(),
            filter_name: $('#filter_name').val(),
            filter_status: $('#filter_status').val(),
        };
        $.ajax({
            url: setConfig_url,
            data: data,
            type: 'post',
            dataType: 'json',
            success: function (response) {
                if (response.success === true) {
                    var href = $(this).attr('href');
                    window.location.href = href + '?' + $('#irForm').serialize();
                }
            }
        })
    });
    
    initOptimizeButton = function () {
        $('button').removeClass('disabled');
        $('#progressbar').css('display', 'none');
        $('.do-optimize-all-action').unbind('click').click(function (e) {
            e.preventDefault();
            $('.optimizeall_flag').val('On');
            optimizeAll();
        });

        $('.stop-optimize-all-action').unbind('click').click(function (e) {
            e.preventDefault();
            $('.optimizeall_flag').val('Off');
            stopOptimizeAll();
        });

    };
    initOptimizeButton();
	
    optimizeAll = function () {
        var data = {
            form_key: window.FORM_KEY,
            filter_type: $('#filter_type').val(),
            filter_name: $('#filter_name').val(),
            filter_status: $('#filter_status').val()
        };
        $.ajax({
            url: optimizeall_url,
            data: data,
            type: 'post',
            dataType: 'json',
            beforeSend: function () {
                $('td > a').addClass('disabled');

            },
            success: function (response) {
                if (response.status == true) {
                    //disableButton();
                    $(".display-time").css("display", "inline-block");
                    $('.do-optimize-all-action').css('display', 'none');
                    $('.stop-optimize-all-action').css('display', 'inline-block');
                    myStatus = setInterval(setStatus, 30000);
                }
            }
        })
    };

    $("#filter_name").keyup(function (e) {
        if (e.keyCode == 13) {
            flag = 1;
            $('.do-search').trigger('click');
        }
        return false;
    });


    $('.item-image').hover(
        function () {
            if ($(this).data('path')) {
                $(this).empty();
                var htmls = ' <img class="image-small" src="' + $(this).data('path') + '" /> ';
                $(this).append(htmls);

            }
        }, function () {
        }
    );
    stopOptimizeAll = function () {
        $.ajax({
            url: stopoptimizeall_url,
            data: {form_key: window.FORM_KEY},
            type: 'post',
            dataType: 'json',
            beforeSend: function () {
                $('a').removeClass('disabled');
                // disableButton();
            },
            success: function (response) {
                if (response.status == false) {
                    $('.stop-optimize-all-action').css('display', 'none');
                    $('.do-optimize-all-action').css('display', 'inline-block');

                }
            }
        })
    };

    function setStatus() {

        $.ajax({
            url: responseAjax_url,
            data: {form_key: window.FORM_KEY},
            type: 'post',
            dataType: 'json',
            success: function (response) {
                if (response.status == true) {

                    if (response.datas.op_status == "On") {
                        //Do not show time
                        return;
                        if(window.startTime == undefined){
                            window.startTime = Date.now();
                            window.startCount = response.datas.optimizedCount;
                        }
                        displayTimedetail(window.startCount - response.datas.optimizedCount, window.startTime - Date.now());
                    } else {
                        $('.do-optimize-all-action').css('display', 'inline-block');
                        $('.stop-optimize-all-action').css('display', 'none');
                        clearInterval(myStatus);
                        window.location.reload();
                    }

                }
            }
        })
    }


    displayTimedetail = function (param1, param2) {
        var reTime = param1 * param2 / 1000;
        var remainTimeStr = toHHMMSS(Math.floor(reTime));

        if (reTime === 0) {
            $(".display-time").css("color", "black").html("Please wait while calculating remaining time");
        }   $(".display-time").css("color", "blue").html("remaining time " + remainTimeStr);
    };

    toHHMMSS = function (sec_num) {
        var hours = Math.floor(sec_num / 3600);
        var minutes = Math.floor((sec_num - (hours * 3600)) / 60);
        var seconds = sec_num - (hours * 3600) - (minutes * 60);
        if (minutes < 10) {
            minutes = "0" + minutes;
        }
        if (seconds < 10) {
            seconds = "0" + seconds;
        }
        var time = '';
        if (hours == 0) {
            time = minutes + 'm ' + seconds + 's';
        } else {
            if (hours < 10) {
                hours = "0" + hours;
            }
            time = hours + 'h ' + minutes + 'm ' + seconds + 's';
        }
        return time;
    };

    $('.disabled').bind('click', function (e) {
        e.preventDefault();
    });

    function allcontroldisable() {
        $(".display-time").css("display", "inline-block");
        $('.do-optimize-all-action').css('display', 'none');
        $('.stop-optimize-all-action').css('display', 'inline-block');
        $('.do-bulk-action').addClass('disabled');
        $('td > a').addClass('disabled');
    }

    function disableButton() {
        $(".imagerecycle-container .button").attr('disabled', 'disabled');
    }

    function enableButton() {
        $(".imagerecycle-container .button").attr('disabled', null);
    }
});
