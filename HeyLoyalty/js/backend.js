var Data = function($type){
  var $that = this;
  var $start = 0;
  var $stop = 100;
  var $nextStep = $stop - $start;
  $that.type = $type;
  var sendRequest = function(callback){
    var requests = {};
    requests.start = $start;
    requests.stop = $stop;
    requests.generateData = 1;
    $.ajax({
      url: window.location.href,
      type: 'POST',
      data: requests,
      dataType: 'json',
      success: function($res){
        console.log($res);
        $limit = $res.limit;
        $start += $nextStep;
        $stop += $nextStep;
        $( '.update-progressbar' ).attr('value', ($stop / $limit) * 100);
        if($start <= $limit){
          sendRequest(callback);
        }else{
          if(typeof callback == 'function')
          callback();
        }
      }
    });
  };
  $that.generate = function(callback){
    sendRequest(callback);
  };
};

function doCSVGenerate(callback){
  var $csvdata = new Data('CSV');
  $( '.update-progressbar' ).attr('value', 0).css('display', 'block');
  $csvdata.generate(callback);
}

function logProgress($log){
  $('#importProgress').html($log);
}

var process = null;

(function($){
  $('#updateData').on('click', function(){
    doCSVGenerate();
    return false;
  });

  function manualImport($first){
    var $only_subscribed = $('#only_subscribed:checked').length;
    $.ajax({
      url: '//' + window.location.host + baseDir + 'modules/HeyLoyalty/API/index.php',
      type: 'GET',
      data: {
        only_subscribed: $only_subscribed,
        cron: 1,
        first: $first,
        id_list: $('#changeList').val()
      },

      success: function(res){
        if(res != 'Import Finished' && res != 'import is already running'){
          if(res != ''){
            logProgress(res);
          }
          setTimeout(function(){
            manualImport(0);
          }, 1500);
        }else{
          logProgress(res);
        }
      },

      error: function(){
        setTimeout(function(){
          manualImport(0);
        }, 1500);
      }
    });
  }

  $('#ImportNow').on('click', function(){
    var $id_list = $('#changeList').val();
    if(!$id_list){
      alert('Please select a list');
      return false;
    }

    $('#importProgress').fadeIn();
    $('#importProgress').html('');
    logProgress('Checking for Customer Updates');
    doCSVGenerate(function(){
      logProgress('Importing Now');
      manualImport(1);
    });

    return false;
  });

  $('#exportHeyLoyaltyExcel, #exportHeyLoyaltyCSV').on('click', function(e){
    $('#importProgress').fadeIn();
    $('#importProgress').html('');
    logProgress('Checking for Customer Updates');
    var $current_link = window.location.href;
    var $action = $(this).attr('name');
    var $only_subscribed = $('#only_subscribed:checked').length;
    doCSVGenerate(function(){
      logProgress('Exporting Now');
      $exportLink = $current_link + '&' + $action + '=1&only_subscribed=' + $only_subscribed;
      window.location = $exportLink;
    });
    return false;
  });

  $('#changeList').on('change', function(){
    var $value = $(this).val();
    document.cookie = "changeList=" + $value;
    document.getElementById('cronListID').innerHTML = $value;
  });

})(jQuery);
