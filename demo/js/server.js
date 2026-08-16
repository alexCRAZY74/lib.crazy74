function doAjaxError(jqXHR, textStatus, errorThrown) {
  $('body').removeClass('loading');

  console.group(`Ошибка: ${textStatus}`);
  console.log({ errorThrown, jqXHR });
  console.error(errorThrown);
  console.groupEnd();

  let msgText = 'Ошибка соединения!';

  if (typeof errorThrown === 'string' && errorThrown.trim() !== '') {
    msgText = `Ошибка: ${errorThrown}`;
  }
  if (textStatus === 'parsererror') {
    msgText = 'Ошибка обработки данных!';
  }
  if (errorThrown === 'timeout') {
    msgText = 'Превышено время ожидания ответа';
  }

  if (typeof jqXHR?.responseText === 'string') {
    msgText += `<br/><br/><div class="highlight" style="font-size:0.85em;">${jqXHR.responseText}</div>`;
  }

  console.log(msgText);

  if (typeof window.jNomad?.modal?.alert === 'function') {
    window.jNomad.modal.alert({
      class: 'error',
      title: 'Ошибка соединения!',
      text: msgText,
    });
  }

  return msgText;
}

function Server(...args) {
  let indicateLoading = false;
  let callback = null;
  let sendData = {};
  const strParams = [];

  const defaultClass = typeof this?.defaultAjaxClass === 'string' ? this.defaultAjaxClass : null;

  for (const arg of args) {
    if (typeof arg === 'boolean') {
      indicateLoading = arg;
    } else if (typeof arg === 'string') {
      strParams.push(arg);
    } else if (typeof arg === 'object' && arg !== null) {
      sendData = arg;
    } else if (typeof arg === 'function') {
      callback = arg;
    }
  }

  const params = {};
  if (defaultClass) {
    params.class = defaultClass;
  }

  if (strParams.length === 1) {
    params.method = strParams[0];
  } else if (strParams.length > 1) {
    params.class = strParams[0];
    params.method = strParams[1];
  }

  params.random = Math.floor(Math.random() * 100000);

  if (!params.class) {
    return;
  }

  const targetUrl = `${appURL}?${$.param(params)}`;
  const dataType = sendData.as_html ? 'text' : 'json';

  if (indicateLoading) {
    $('body').addClass('loading');
  }

  return $.ajax({
    url: targetUrl,
    type: 'POST',
    data: sendData,
    dataType,
    error: doAjaxError,
    success: (data) => {
      if (indicateLoading) {
        $('body').removeClass('loading');
      }
      if (typeof callback === 'function') {
        if (typeof sendData.idrow !== 'undefined') {
          callback.call(this, data, sendData.idrow);
        } else {
          callback.call(this, data);
        }
      }
    }
  });
}