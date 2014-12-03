/**
 * Created by DnAp on 08.05.14.
 */
var topWindow = parent;

while(topWindow != topWindow.parent) {
    topWindow = topWindow.parent;
}

if(typeof(topWindow.DDeliveryIntegration) == 'undefined') {
    if(topWindow != window){
        var DDeliveryIntegration = {getStatus: function(){
            if(typeof(topWindow.DDeliveryIntegration) == 'undefined') {
                return '\u0412\u044b\u0431\u0435\u0440\u0438\u0442\u0435 \u0443\u0441\u043b\u043e\u0432\u0438\u044f \u0434\u043e\u0441\u0442\u0430\u0432\u043a\u0438';
            }else{
                DDeliveryIntegration = topWindow.DDeliveryIntegration;
                return DDeliveryIntegration.getStatus();
            }
        }};
        (function(){
            var script = document.createElement('script');
            script.src = "/bitrix/components/ddelivery/static/include.js";
            script.charset = "utf-8";
            topWindow.document.getElementsByTagName('head')[0].appendChild(script);
            script = document.createElement('script');
            script.src = "/bitrix/components/ddelivery/static/js/ddelivery.js";
            script.charset = "utf-8";
            topWindow.document.getElementsByTagName('head')[0].appendChild(script);
        })();

    }else{
        topWindow.DDeliveryIntegration = (function(){
            var th = {};
            var status = '\u0412\u044b\u0431\u0435\u0440\u0438\u0442\u0435 \u0443\u0441\u043b\u043e\u0432\u0438\u044f \u0434\u043e\u0441\u0442\u0430\u0432\u043a\u0438';
            th.getStatus = function(){
                return status;
            };
            var document = topWindow.document;

            function hideCover() {
                document.body.removeChild(document.getElementById('ddelivery_cover'));
            }

            function showPrompt() {
                var cover = document.createElement('div');
                cover.id = 'ddelivery_cover';
                document.body.appendChild(cover);
                document.getElementById('ddelivery_container').style.display = 'block';
            }

            function buildUrlParam(obj)
            {
                var s = [];
                var add = function(k, v){
                    s[ s.length ] = encodeURIComponent( k ) + "=" + encodeURIComponent( v );
                };
                var build = function(prefix, obj) {
                    if(typeof obj == 'object') {
                        for(var name in obj) {
                            if(prefix.length == 0) {
                                build(name, obj[name]);
                            }else{
                                build(prefix + "[" + name + "]", obj[name]);
                            }
                        }
                    } else {
                        add(prefix, obj);
                    }
                };

                build('', obj);

                return s.join('&');
            }

            th.openPopup = function(){
                showPrompt();
                document.getElementById('ddelivery_popup').innerHTML = '';
                //jQuery('#ddelivery_popup').html('').modal().open();
                var params = {
                    formData: {}
                };
                var form = document.getElementById('ORDER_FORM');
                if(form == null) {
                    form = document.getElementById('ORDER_FORM_ID_NEW');
                }
                var curEl,
                    rinput = /^(?:color|date|datetime|datetime-local|email|hidden|month|number|password|range|search|tel|text|time|url|week)$/i,
                    rselectTextarea = /^(?:select|textarea)/i;
                for(var i = 0; i < form.length ; i++) {
                    curEl = form[i];
                    if(curEl.name && !curEl.disabled && ( curEl.checked || rselectTextarea.test( curEl.nodeName ) || rinput.test( curEl.type ) )) {
                        params.formData[curEl.name] = curEl.value;
                    }
                }

                var callback = {
                    close: function(){
                        hideCover();
                        document.getElementById('ddelivery_container').style.display = 'none';
                    },
                    change: function(data) {
                        status = data.comment;
                        document.getElementById('ddelivery').getElementsByTagName('SPAN').innerHTML = data.comment;

                        hideCover();
                        document.getElementById('ddelivery_container').style.display = 'none';

                        document.getElementById('ID_DELIVERY_ddelivery_all').click();
                    }
                };

                DDelivery.delivery('ddelivery_popup', '/bitrix/components/ddelivery/static/ajax.php?'+buildUrlParam(params), {/*orderId: 4*/}, callback);

                return void(0);
            };
            var style = document.createElement('STYLE');
            style.innerHTML = // Скрываем ненужную кнопку
                " #delivery_info_ddelivery_all a{display: none;} " +
                " #ddelivery_popup { display: inline-block; vertical-align: middle; margin: 10px auto; width: 1000px; height: 650px;} " +
                " #ddelivery_container { position: fixed; top: 0; left: 0; z-index: 9999;display: none; width: 100%; height: 100%; text-align: center;  } " +
                //" #ddelivery_container:before { display: inline-block; height: 100%; content: ''; vertical-align: middle;} " +
                " #ddelivery_cover {  position: fixed; top: 0; left: 0; z-index: 9000; width: 100%; height: 100%; background-color: #000; background: rgba(0, 0, 0, 0.5); filter: progid:DXImageTransform.Microsoft.gradient(startColorstr = #7F000000, endColorstr = #7F000000); } ";
            var body = document.getElementsByTagName('body')[0];
            body.appendChild(style);
            var div = document.createElement('div');
            div.innerHTML = '<div id="ddelivery_popup"></div>';
            div.id = 'ddelivery_container';
            body.appendChild(div);

            return th;
        })();
        var DDeliveryIntegration = topWindow.DDeliveryIntegration;
    }
}else{
    var DDeliveryIntegration = topWindow.DDeliveryIntegration;
}
