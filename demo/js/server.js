function doAjaxError(jqXHR, textStatus, errorThrown) {
  $('body').reamoveClass('loading');
  console.group('Ошибка: '+textStatus);
  console.log({errorThrown:errorThrown,jqXHR:jqXHR});
  console.error(errorThrown);
  console.groupEnd();
  var msg_text = 'Ошибка соединения!';
  if (typeof(errorThrown) == 'string' && errorThrown != '') msg_text = 'Ошибка: '+errorThrown;
  if (textStatus == 'parsererror') msg_text = 'Ошибка обработки данных!';
  if (errorThrown == 'timeout') msg_text = 'Превышено время ожидания ответа';
  if (typeof(jqXHR) != 'undefined' && typeof(jqXHR.responseText) == 'string') {
    msg_text = msg_text + '<br/><br/><div class="highlight" style="font-size:0.85em;">' + jqXHR.responseText + '</div>';
  }
  console.log(msg_text);
  if (typeof(jNomad) != 'undefined' && typeof(jNomad.modal.alert) == 'function') {
    jNomad.modal.alert({class:'error',title:'Ошибка соединения!',text:msg_text});
  }
  return msg_text;
}
function Server(){
  //console.warn({Server:this});
  var indicateLoading = false;
  var Callback = false;
  var sendData = {};
  var urlLine = {
    url:appURL,
    params: {
    },
    get: function(){
      return this.url+'?'+$.param(this.params);
    }
  };
  var params = urlLine.params;
  if (typeof(this) != 'undefined' && typeof(this.defaultAjaxClass) == 'string') {
    params.class = this.defaultAjaxClass;
  }
  var strparams = [];
  for (var key in arguments) {
    var arg = arguments[key];
    switch (typeof(arg)) {
      case 'boolean':
        indicateLoading = arg;
        break;
      case 'string':
        strparams.push(arg);
        break;
      case 'object':
        //sendData = $.extend(true,{},sendData,arg);
        sendData = arg;
        break;
      case 'function':
        Callback = arg;
        break;
    }
  }
  if (strparams.length == 1) {
    params.method = strparams[0];
  } else if (strparams.length > 1) {
    params.class = strparams[0];
    params.method = strparams[1];
  }
  params.random = Math.floor(Math.random()*100000);
  var instance = this;
  if (typeof(window.clientTimezone) != 'string' || window.clientTimezone == '') {
    sendData['_timezone'] = Intl.DateTimeFormat().resolvedOptions().timeZone;
  }
  var ajaxParams = { 
    url:urlLine.get(),
    type:'POST',
    data: sendData,
    dataType:"json",
    error:doAjaxError,
    success:function(data){
      if (indicateLoading) $('body').removeClass('loading');
      if (typeof(Callback)=='function') {
        if (typeof(sendData.idrow) != 'undefined') {
          Callback.call(instance,data,sendData.idrow);
        } else {
          Callback.call(instance,data);
        }
      }
    }
  };
  if (sendData.as_html) {
    ajaxParams.dataType = 'text';
  }
  //console.info({indicateLoading:indicateLoading,Callback:Callback,sendData:sendData,urlLine:urlLine,params:params,ajaxParams:ajaxParams});
  if (typeof(params.class) != 'undefined') {
    if (indicateLoading) $('body').addClass('loading');
    return $.ajax(ajaxParams);
  }
}
