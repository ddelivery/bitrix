/**
 * Created by DnAp on 08.05.14.
 */
if(typeof(DDeliveryIntegration) == 'undefined')
    var DDeliveryIntegration = (function(){
        var th = {};
        var status = 'Выберите точку самовывоза';
        th.getStatus = function(){
            return status;
        };

        th.openPopup = function(){
            jQuery('#ddelivery_popup').html('').modal().open();
            var params = {
                formData: {}
            };
            $($('#ORDER_FORM').serializeArray()).each(function(){
                params.formData[this.name] = this.value;
            });

            var callback = {
                close: function(){
                    jQuery.modal().close();
                },
                change: function(data) {
                    status = data.comment;
                    $('#ddelivery span').html(data.comment);
                    jQuery.modal().close();
                    $('#ID_DELIVERY_ddelivery_all').click();
                }
            };

            DDelivery.delivery('ddelivery_popup', '/bitrix/components/ddelivery/static/ajax.php?'+$.param(params), {/*orderId: 4*/}, callback);

            return void(0);
        };


        $('BODY').append("<style>" +
            // Скрываем ненужную кнопку
            " #delivery_info_ddelivery_all a{display: none;} " +
            // Стили попапа
            " .modal { background: #eee; width: 1000px; margin: 10px auto; border: 3px solid #666; padding: 0px; } " +
            " .modal .close { float: right; text-decoration: none; font-size: 40px; cursor: pointer; } " +
            " .themodal-lock { overflow: hidden; } " +
            " .themodal-overlay { position: fixed; bottom: 0; left: 0; top: 0;right: 0; z-index: 100; overflow: auto; -webkit-overflow-scrolling: touch; } " +
            " .themodal-overlay > * { -webkit-transform: translateZ(0px); } " +
            " .themodal-overlay { background: rgba(0, 0, 0, 0.5); filter: progid:DXImageTransform.Microsoft.gradient(startColorstr = #7F000000, endColorstr = #7F000000); zoom: 1; z-index:1000; } " +
            "</style>" +
            '<div class="modal" id="ddelivery_popup" style="display: none"></div>');

        return th;
    })();
