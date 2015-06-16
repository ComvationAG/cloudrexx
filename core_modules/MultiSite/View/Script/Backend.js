(function($) {
    cx.ready(function() {
        $('#instance_table').append('<div id ="load-lock"></div>');
        
        cx.multisite();
        
        cx.multisite.ajaxResponse = '';
        
        $(".defaultCodeBase").change(function() {
            domainUrl = cx.variables.get('baseUrl', 'MultiSite') + cx.variables.get('cadminPath', 'contrexx') + "index.php?cmd=JsonData&object=MultiSite&act=updateDefaultCodeBase";
            cx.jQuery.ajax({
                dataType: "json",
                url: domainUrl,
                data: {
                    defaultCodeBase: $(this).val(),
                },
                type: "POST",
               
                success: function(response) {
                    if (response.data) {
                        cx.tools.StatusMessage.showMessage(response.data, null,2000);
                    }
                }

            });
        });
        $('.changeWebsiteStatus').focus(function() {
//Store old value
            $(this).data('lastValue', $(this).val());
            
            cx.bind("loadingStart", cx.lock, "websitestatus");
            cx.bind("loadingEnd", cx.unlock, "websitestatus");
            //changing dropdown value
        }).change(function() {
            domainUrl = cx.variables.get('baseUrl', 'MultiSite') + cx.variables.get('cadminPath', 'contrexx') + "index.php?cmd=JsonData&object=MultiSite&act=updateWebsiteState";
            var websiteDetails = $(this).attr('data-websiteDetails').split("-");
            if (confirm("Please confirm to change the state of website " + websiteDetails[1] + ' to ' + $(this).val())) {
                cx.trigger("loadingStart", "websitestatus", {});
                cx.tools.StatusMessage.showMessage("<div id=\"loading\">" + cx.jQuery('#loading').html() + "</div>");
                cx.jQuery.ajax({
                    dataType: "json",
                    url: domainUrl,
                    data: {
                        websiteId: websiteDetails[0],
                        status: $(this).val()
                    },
                    type: "POST",
                    success: function(response) {
                        if (response.data) {
                            cx.tools.StatusMessage.showMessage(response.data, null, 2000);
                        }
                        cx.trigger("loadingEnd", "websitestatus", {});
                    }

                });
            }else{
                $(this).val($(this).data('lastValue'));
            }
        });
        /**
         * Locks the website status in order to prevent user input
         */
        cx.lock = function() {
            cx.jQuery("#load-lock").show();
            cx.jQuery("#MultisiteConfigload-lock").show();
        };
        /**
         * Unlocks the website status in order to allow user input
         */
        cx.unlock = function() {
            cx.jQuery("#load-lock").hide();
            cx.jQuery("#MultisiteConfigload-lock").hide();
        };
        // show license
        $('.showLicense').click(function() {
            cx.bind("loadingStart", cx.lock, "showLicense");
            cx.bind("loadingEnd", cx.unlock, "showLicense");
            cx.trigger("loadingStart", "showLicense", {});
            
            var className = $(this).attr('class'),
                id        = parseInt(className.match(/[0-9]+/)[0], 10),
                title     = cx.variables.get('getLicenseTitle', "multisite/lang") + $(this).data('websitename');
            
            domainUrl = cx.variables.get('baseUrl', 'MultiSite') + cx.variables.get('cadminPath', 'contrexx') + "index.php?cmd=JsonData&object=MultiSite&act=getLicense";                        
            $.ajax({
                url: domainUrl,
                type: "POST",
                data: {command: 'getLicense', websiteId: id},
                dataType: "json",
                beforeSend : function() {
                    cx.tools.StatusMessage.showMessage("<div id=\"loading\">" + $('#loading').html() + "</div>");
                },
                success: function(response) {
                    cx.trigger("loadingEnd", "showLicense", {});
                    if (response.status == 'success') {
                        switch (response.data.status) {
                            case 'success':
                                if (response.data.result != 'undefined') {
                                    $table = $('<table cellspacing="0" cellpadding="3" border="0" class="adminlist" width="100%" />');
                                    $.each(response.data.result, function(key, data) {
                                        $tr = $('<tr />');
                                        $('<td />')
                                                .html(key)
                                                .appendTo($tr);

                                        container = $('<div />')
                                                .attr('id', key);
                                        licenseContent = data.content;
                                        if (typeof licenseContent === 'object') {
                                            jsonString = JSON.stringify(licenseContent);
                                            $('<span />')
                                                    .attr('id', 'ui_' + key)
                                                    .html(jsonString)
                                                    .hide()
                                                    .appendTo(container);
                                            textContent = $('<span />');

                                            $.each(licenseContent, function(key, data) {
                                                if (typeof data === 'object') {
                                                    $('<span />')
                                                            .addClass('ui_license ' + data.lang_name)
                                                            .css('font-weight', 'bold')
                                                            .html(data.lang_name)
                                                            .appendTo(textContent);
                                                    textContent.append(':&nbsp;');
                                                    $('<span />')
                                                            .addClass('ui_licenseMsg langId_' + data.lang_id)
                                                            .html(data.message)
                                                            .appendTo(textContent);
                                                    textContent.append('<br />');
                                                    Multisite.availableLanguages[data.lang_id] = data.lang_name;
                                                } else {
                                                    textContent.append(key + ' : ' + data + ', ')
                                                }
                                            });
                                            container.append(textContent);
                                        } else {
                                            $('<span >')
                                                    .html(licenseContent)
                                                    .appendTo(container);
                                        }
                                        $('<a >')
                                                .attr('href', 'javascript:void(0);')
                                                .attr('title', 'Edit License Information')
                                                .addClass('editLicense editLicenseData editLicense_' + key)
                                                .data('field', key)
                                                .data('websiteid', id)
                                                .data('value', licenseContent)
                                                .data('options', data.values)
                                                .data('editType', data.type)
                                                .click(function() {
                                                    Multisite.editLicense($(this));
                                                })
                                                .appendTo(container);
                                        $('<td />')
                                                .append(container)
                                                .appendTo($tr);

                                        $table.append($tr);
                                    });

                                } else {
                                    $table = $('<span>').html('No data found!');
                                }
                                cx.tools.StatusMessage.showMessage(cx.variables.get('licenseInfo', "multisite/lang"), null, 3000);
                                cx.ui.dialog({
                                    width: 820,
                                    height: 400,
                                    title: title,
                                    content: $('<div />').append($table),
                                    autoOpen: true,
                                    modal: true,
                                    buttons: {
                                        "Close": function() {
                                            $(this).dialog("close");
                                        }
                                    }
                                });
                                break;
                            case 'error':
                                cx.tools.StatusMessage.showMessage(response.data.message, null, 4000);
                                break;
                            default:
                                break;
                        }
                    } else {
                        cx.tools.StatusMessage.showMessage(response.message, null, 4000);
                    }
                }
            });
        });
        
        // get mail service server plans
        $('.mailServerPlans').click(function () {
            cx.bind("loadingStart", cx.lock, "mailServerPlans");
            cx.bind("loadingEnd", cx.unlock, "mailServerPlans");
            cx.trigger("loadingStart", "mailServerPlans", {});

            var className = $(this).attr('class'),
                    id = parseInt(className.match(/[0-9]+/)[0], 10),
                    title = $(this).attr('title');

            Url = cx.variables.get('baseUrl', 'MultiSite') + cx.variables.get('cadminPath', 'contrexx') + "index.php?cmd=JsonData&object=MultiSite&act=getMailServicePlans";
            $.ajax({
                url: Url,
                type: "POST",
                data: {mailServiceServerId: id},
                dataType: "json",
                beforeSend: function () {
                    cx.tools.StatusMessage.showMessage("<div id=\"loading\">" + $('#loading').html() + "</div>");
                },
                success: function (response) {
                    cx.tools.StatusMessage.removeAllDialogs();
                    cx.trigger("loadingEnd", "mailServerPlans", {});
                    if (response.status == 'success' && response.data.status == 'success') {
                        if(jQuery.type(response.data.result) === 'string') {
                            $htmlContent = '<p>' + response.data.result + '</p>';
                        } else {
                            $htmlContent = $('<table cellspacing="0" cellpadding="3" border="0" class="adminlist" width="100%" />');
                            $htmlContent.append('<thead><th>Name</th><th>GUID</th></thead>');
                            $.each(response.data.result, function(key, data) {
                                $tr = $('<tr class = "row1" />');
                                $('<td />')
                                        .html(key)
                                        .appendTo($tr);
                                $('<td />')
                                        .html(data)
                                        .appendTo($tr);
                                $htmlContent.append($tr);
                            });
                        }
                        cx.ui.dialog({
                            width: 820,
                            height: 400,
                            title: title,
                            content: $('<div />').append($htmlContent),
                            autoOpen: true,
                            modal: true,
                            buttons: {
                                "Close": function() {
                                    $(this).dialog("close");
                                }
                            }
                        });                        
                    } else {
                        var errorMessage =  (response.message) === '' ?  (response.data.message) : (response.message);
                        cx.tools.StatusMessage.showMessage(errorMessage, null, 4000);
                    }
                }
            });
        });
        
        // execute query on websites / service server's websites
        $('.executeQuery').click(function() {
            cx.bind('loadingStart', executeQueryLock, 'executeSql');
            cx.bind('loadingEnd', executeQueryUnlock, 'executeSql');

            var title = $(this).attr('title'),
                paramsArr = ($(this).attr('data-params')).split(':'),
                argName = paramsArr[0],
                argValue = paramsArr[1],
                initialContent = '<div><form id="executeSql"><div id="statusMsg"></div><div class="resultSet"></div><textarea rows="10" cols="100" class="queryContent" name="executeQuery"></textarea></form></div>',
                buttons = [
                    {
                        text: "Cancel",                            
                        click: function() {
                            cx.multisite.ajaxResponse.abort();
                            cx.trigger("loadingEnd", 'multisiteAjaxcall', {});
                            cx.tools.StatusMessage.removeAllDialogs();
                            $(this).dialog('close');
                        }
                    },
                    {
                        text: "Execute",
                        class: "executeQuery",
                        click: function() {
                            var query = $('.queryContent').val();
                            if ($.trim(query) == '') {
                                cx.tools.StatusMessage.showMessage(cx.variables.get('plsInsertQuery', "multisite/lang"), null, 3000);
                                return false;
                            } else {
                                cx.tools.StatusMessage.showMessage("<div id=\"loading\">" + cx.jQuery('#loading').html() + "</div>");
                                cx.trigger('loadingStart', 'executeSql', {});
                                $('.resultSet').html('');
                                cx.multisite.MultisiteAjaxCall(
                                        cx.variables.get('baseUrl', 'MultiSite') + cx.variables.get('cadminPath', 'contrexx') + "index.php?cmd=JsonData&object=MultiSite&act=executeSql",
                                        {
                                            query: query,
                                            mode: argName,
                                            id: argValue,
                                            command: 'executeSql'
                                        },
                                        cx.multisite.callbackSqlQueryExecution
                                );
                            }
                        }
                    },
                    {
                        text: "Stop Execution...",
                        class: "stopexecutionQuery",
                        click: function() {
                            cx.trigger('loadingEnd', 'executeSql', {});
                        }
                    }
                ];
            cx.ui.dialog({
                width: 820,
                height: 400,
                title: title,
                content: initialContent,
                autoOpen: true,
                modal: true,
                buttons: buttons,
                close: function() {
                    $('#executeSql').remove();
                    cx.trigger("loadingEnd", 'multisiteAjaxcall', {});
                    cx.multisite.ajaxResponse.abort();
                    cx.tools.StatusMessage.removeAllDialogs();
                    $(this).dialog('close');
                }
            });
        });

        /**
         * Locks the text area and Execute button in order to prevent user input
         */
        var executeQueryLock = function() {
            $('.queryContent').attr('readonly', true);
            $('.executeQuery.ui-button').hide();
            $('.stopexecutionQuery.ui-button').show();
        };
        
        /**
         * Unlocks the text area and Execute button in order to allow user input
         */
        var executeQueryUnlock = function() {
            $('.queryContent').attr('readonly', false);
            $('.stopexecutionQuery.ui-button').hide();
            $('.executeQuery.ui-button').show();
        };
        
        //Execute Sql Query on website/service 
        cx.multisite.callbackSqlQueryExecution = function(response) {
            if (response.status == 'success') {
                switch (response.mode) {
                    case 'website':
                        if ($('.stopexecutionQuery.ui-button').is(':visible')) {
                            $('.resultSet').html(parseQueryResult(response.queryResult));
                            cx.tools.StatusMessage.showMessage(cx.variables.get('completedMsg', 'multisite/lang'), null, 3000);
                            cx.trigger('loadingEnd', 'executeSql', {});
                        } else {
                            cx.multisite.MultisiteAjaxCall(
                                    cx.variables.get('baseUrl', 'MultiSite') + cx.variables.get('cadminPath', 'contrexx') + "index.php?cmd=JsonData&object=MultiSite&act=stopQueryExecution",
                                    {sessionRandomKey: response.randomKey}
                            );
                        }
                        break;
                    case 'service':
                        cx.multisite.executeSql(response.randomKey);
                        break;
                    default:
                        break;
                }
            } else {
                $('#statusMsg').text(response.message);
                cx.tools.StatusMessage.showMessage(response.message, null, 3000);
                cx.trigger('loadingEnd', 'executeSql', {});
            }
        };
        
        //Execute sql Query By Session
        cx.multisite.callbackExecuteSqlQueryBySession = function(response) {
            switch (response.status) {
                case 'success':
                    offset = 0;
                    cx.tools.StatusMessage.showMessage("<div id=\"loading\">" + cx.jQuery('#loading').html() + "<span> ( " + response.websitesDone + " / " + response.totalWebsites + " ) </span></div>");

                    if (response.queryResult == null) {
                        $('.resultSet').append('<div><table cellspacing="0" cellpadding="3" border="0" class="adminlist" width="100%"><tbody><tr><th>' + response.websiteName + '</th></tr><tr class="row1"><td><div class="alertbox">' + cx.variables.get('errorMsg', 'multisite/lang') + '</div></td></tr></tbody></table></div>');
                    } else {
                        $('.resultSet').append(parseQueryResult(response.queryResult));
                    }

                    $(".resultSet > div:not(:last)").each(function(i, e) {
                        offset += $(e).outerHeight(true);
                    });
                    $('.ui-dialog-content').animate({scrollTop: offset}, 'slow');
                    cx.multisite.executeSql(response.randomKey);
                    break;
                case 'error':
                    cx.tools.StatusMessage.showMessage(cx.variables.get('completedMsg', 'multisite/lang'), null, 3000);
                    cx.trigger('loadingEnd', 'executeSql', {});
                    return;
                    break;
                default:
                    break;
            }
        };
        
        //Multisite AjaxCall Manipulation
        cx.multisite.MultisiteAjaxCall = function (url, data, callback) {            
            cx.bind('loadingStart', cx.lock, 'multisiteAjaxcall');
            cx.bind('loadingEnd', cx.unlock, 'multisiteAjaxcall');
            cx.trigger("loadingStart", 'multisiteAjaxcall', {});
            cx.multisite.ajaxResponse = $.ajax({
                url: url,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.status == 'success') {
                        if (callback && typeof (callback) === "function") {
                            callback(response.data);
                        } else {
                            switch (response.data.status) {
                                case 'success':
                                    cx.tools.StatusMessage.showMessage(response.data.message, null, 2000);
                                    break;
                                case 'error':
                                    cx.tools.StatusMessage.showMessage(response.data.message, null, 4000);
                                    break;
                                default:
                                    break;
                            }
                        }
                    } else {
                        cx.tools.StatusMessage.showMessage(response.message, null, 4000);
                    }
                    cx.trigger("loadingEnd", 'multisiteAjaxcall', {});
                }
            });
        };
        
        /**
         * Show the Executed Query result in dialog window
         */
        var parseQueryResult = function(response) {
            var html = '';
            var resultTable = '';
            if (response.status) {
                var theader = '<table cellspacing="0" cellpadding="3" border="0" class="adminlist" width="100%">';
                var col_count = 0;
                var tbody = "";
                var thead = "";
                if(response.resultSet) {
                    var cols = Object.keys(response.resultSet).length;
                    $.each(response.resultSet, function(resultSetkey, resultSetData) {
                        tbody += "<tr class =row1>";
                        if (col_count == 0) {
                            thead += "<th colspan='2'>" + response.websiteName + "</th>";
                        }
                        if (col_count < cols) {
                            var count = 0;
                            var tsbody = "";
                            var tshead = "";
                            tbody += "<td><div class='" + resultSetData.queryStatus + "'>" + resultSetData.query + "</td>";
                            if (typeof resultSetData.resultValue === 'object') {
                                var no_cols = Object.keys(resultSetData.resultValue).length;
                                $.each(resultSetData.resultValue, function(resultValueKey, resultValueData) {
                                    tsbody += "<tr class =row1>";
                                    for (resultValueKey in resultValueData) {
                                        if (count == 0) {
                                            tshead += "<th>";
                                            tshead += resultValueKey;
                                            tshead += "</th>"
                                        }
                                        if (count < no_cols) {
                                            tsbody += "<td>";
                                            tsbody += resultValueData[resultValueKey];
                                            tsbody += "</td>"
                                        }
                                    }
                                    count++;
                                    tsbody += "</tr>";
                                });
                            } else if (resultSetData.resultValue) {
                                tsbody += "<tr class =row1>";
                                tsbody += "<td>";
                                tsbody += resultSetData.resultValue;
                                tsbody += "</td>";
                                tsbody += "</tr>";
                            }
                            resultTable = theader + tshead + tsbody + "</table></br>";
                            tbody += "<td>" + resultTable + "</td>";
                        }
                        col_count++;
                        tbody += "</tr>";
                    });
                    html +=  theader + thead + tbody + "</table>";
                } 
            }
            html = $('<div />').append(html);
            return html;
        };
        
        //Create a backup of website(s)
        $('.websiteBackup').click(function() {
            var iAttr = $(this).data('params').split(':');
            var params = '';
            if (!iAttr[0] || !iAttr[1]) {
                return false;
            }
            
            switch(iAttr[0]) {
                case 'service':
                    params = {serviceServerId: iAttr[1], responseType: 'json'};
                    break;
                case 'website':
                    params = {websiteId: iAttr[1], responseType: 'json'};
                    break;
                default:
                    return false;
                    break;
            }
            if (!confirm(cx.variables.get('websiteBackupConfirm', 'multisite/lang'))) {
                return false;
            }
            
            cx.bind("loadingStart", cx.lock, "websiteBackup");
            cx.bind("loadingEnd", cx.unlock, "websiteBackup");
            $.ajax({
                url: cx.variables.get('cadminPath', 'contrexx') + "?cmd=JsonData&object=MultiSite&act=triggerWebsiteBackup",
                data: params,
                type: "POST",
                dataType: "json",
                beforeSend: function() {
                    cx.trigger("loadingStart", "websiteBackup", {});
                    cx.tools.StatusMessage.showMessage("<div id=\"loading\" class = \"websiteBackup\">" + cx.jQuery('#loading').html() +"</div>");
                    $('#loading > span').html(cx.variables.get('websiteInProgress', 'multisite/lang'));
                },
                success: function(response) {
                    var $resp = (response.data) ? response.data : response;
                    cx.tools.StatusMessage.showMessage($resp.message ? $resp.message : $resp, null, 2000);
                    cx.trigger("loadingEnd", "websiteBackup", {});
                }
            });
            
        });

        //Website restore by clicking restore button
        $('.websiteRestore').click(function() {
            var userId  = $(this).attr('data-userId') != undefined ? $(this).attr('data-userId') : 0;
            var params = {backupedServiceServer: $(this).attr('data-serviceId'), websiteBackupFileName: $(this).attr('data-backupFile')};
            Multisite.websiteRestore(params, false, userId);
        });
        
        //Remove Backuped website in the service server
        $('.deleteWebsiteBackup').click(function() {
            if (!confirm(cx.variables.get('websiteBackupDeleteConfirm', 'multisite/lang'))) {
                return false;
            }
            
            cx.bind("loadingStart", cx.lock, "deleteWebsiteBackup");
            cx.bind("loadingEnd", cx.unlock, "deleteWebsiteBackup");
            $.ajax({
                url: cx.variables.get('cadminPath', 'contrexx') + "?cmd=JsonData&object=MultiSite&act=triggerWebsiteBackup",
                data: {serviceServerId: $(this).attr('data-serviceId'), websiteBackupFileName: $(this).attr('data-backupFile')},
                type: "POST",
                dataType: "json",
                beforeSend: function() {
                    cx.trigger("loadingStart", "deleteWebsiteBackup", {});
                    cx.tools.StatusMessage.showMessage("<div id=\"loading\">" + cx.jQuery('#loading').html() +"</div>");
                    $('#loading > span').html(cx.variables.get('websiteBackupDeleteInProgress', 'multisite/lang'));
                },
                success: function(response) {
                    var $resp = (response.data) ? response.data : response;
                    if ($resp.status == 'success') {
                        location.reload();
                    }
                    cx.tools.StatusMessage.showMessage($resp.message, null, 2000);
                    cx.trigger("loadingEnd", "deleteWebsiteBackup", {});
                }
            });
        });
        
        //Login to remote website when the following click operation is performed
        $('.remoteWebsiteLogin').click(function() {
            cx.bind("loadingStart", cx.lock, "remoteLogin");
            cx.bind("loadingEnd", cx.unlock, "remoteLogin");
            cx.trigger("loadingStart", "remoteLogin", {});
            cx.tools.StatusMessage.showMessage("<div id=\"loading\">" + cx.jQuery('#loading').html() + "</div>");
            domainUrl = cx.variables.get('baseUrl', 'MultiSite') + cx.variables.get('cadminPath', 'contrexx') + "index.php?cmd=JsonData&object=MultiSite&act=remoteLogin";
            $.ajax({
                url: domainUrl,
                type: "POST",
                data: {websiteId: $(this).attr('data-id'), loginType: $(this).data('login')},
                dataType: "json",
                success: function(response) {
                    if (response.status == 'success') {
                        switch(response.data.status) {
                            case 'success':
                                cx.tools.StatusMessage.showMessage(response.data.message, null, 2000);
                                window.open(response.data.webSiteLoginUrl, '_blank');
                                break;
                            case 'error':
                                cx.tools.StatusMessage.showMessage(response.data.message, null, 4000);
                                break;
                            default:
                                break;
                        }
                    } else {
                        cx.tools.StatusMessage.showMessage(response.message, null, 4000);
                    }
                    cx.trigger("loadingEnd", "remoteLogin", {});
                }
            });
        });
        
    $('.websiteUpdate').click(function () {
      cx.bind("loadingStart", cx.lock, "websiteUpdate");
      cx.bind("loadingEnd", cx.unlock, "websiteUpdate");
      domainUrl = cx.variables.get('baseUrl', 'MultiSite') + cx.variables.get('cadminPath', 'contrexx') + "index.php?cmd=JsonData&object=MultiSite&act=";
      var serviceServerId = $(this).data('id');
      $.ajax({
        url: domainUrl + 'getCodeBaseVersions',
        type: "POST",
        data: {serviceServerId: serviceServerId},
        dataType: "json",
        beforeSend: function () {
          cx.trigger("loadingStart", "websiteUpdate", {});
          cx.tools.StatusMessage.showMessage("<div id=\"loading\">" + cx.jQuery('#loading').html() + "</div>");
          $('#loading > span').html(cx.variables.get('loadingServiceServerInfo', 'multisite/lang'));
        },
        success: function (response) {
          if (response.status == 'success') {
            switch (response.data.status) {
              case 'success':
                cx.tools.StatusMessage.showMessage(response.data.message, null, 2000);
                //websiteCodeBase versions
                var codeBaseDropDown = $('<select />').attr('name', 'codeBase')
                                          .change(function () {
                                            //get websites by codeBase
                                            getWebsiteByCodeBase(domainUrl, serviceServerId, $(this).val());
                                          });
                $(response.data.codeBaseVersions).each(function (index, codeBaseVersion) {
                  codeBaseDropDown.append($('<option />').text(codeBaseVersion));
                });

                var dialogContent = cx.variables.get('updateNotAvailable', 'multisite/lang');
                if (response.data.codeBaseVersions != '') {
                    dialogContent = $('<form>').attr({'id': 'websiteUpdateForm', 'name': 'frmShowWebsites'})
                            .append($('<label/>').text(cx.variables.get('latestCodeBaseVersion', 'multisite/lang'))
                                    .append(codeBaseDropDown)).append('<br/>')
                            .append($('<div/>').attr('id', 'websitesSection'))
                            .submit(function () {
                              var data = $(this).serialize() + '&serviceServerId=' + serviceServerId;
                              var url = domainUrl + 'triggerWebsiteUpdate';
                              triggerWebsiteUpdate(url, data);
                              return false;
                            });
                }
                cx.ui.dialog({
                  width: 820,
                  height: 400,
                  title: 'Update',
                  content: dialogContent,
                  autoOpen: true,
                  modal: true
                });
                var codeBase = $("select[name='codeBase']").val();
                if (codeBase != null) {
                  getWebsiteByCodeBase(domainUrl, serviceServerId, codeBase);
                }
                break;
              case 'error':
                cx.tools.StatusMessage.showMessage(response.data.message, null, 4000);
                break;
              default:
                break;
            }

          } else {
            cx.tools.StatusMessage.showMessage(response.message, null, 4000);
          }
          cx.trigger("loadingEnd", "websiteUpdate", {});
        }
      });
    });
    
    function getWebsiteByCodeBase(domainUrl, serviceId, codeBase) {
      $('div').find('#websitesSection').html('<div id="loading">' + cx.variables.get('loading', 'multisite/lang') + '</div');
      $.ajax({
        url: domainUrl + 'getWebsitesByCodeBase',
        type: "POST",
        data: {codeBase: codeBase, serviceServerId: serviceId},
        dataType: "json",
        success: function (response) {
          if (response.status == 'success') {
            //websites
            var table = $('<table />')
                             .addClass('adminlist')
                              .attr({"align" : "center" });
                        table.append($('<tr />')
                             .append($('<th />').text("#"))
                             .append($('<th />').text(cx.variables.get('websiteName', 'multisite/lang')))
                             .append($('<th />').text(cx.variables.get('codeBase', 'multisite/lang')))
                           );
            $(response.data.websites).each(function (key, websites) {
              var tableRow = ($('<tr />'));
              $(websites).each(function (index, website) {
                tableRow.append($('<td />').append($('<input/>').attr({'type':'checkbox', 'name':'websites[]' }).val(website.id)));
                tableRow.append($('<td />').text(website.name));
                tableRow.append($('<td />').text(website.codeBase));
                table.append(tableRow);
              });
            });
          }
          var htmlElement = $('div').find('#websitesSection');
          if (response.data.websites == '') {
            htmlElement.html(cx.variables.get('websitesNotExist', 'multisite/lang'));
          } else {
            htmlElement.html(table);
            htmlElement.append($('<a />')
                                  .attr("onclick", "changeCheckboxes(\'frmShowWebsites\',\'websites[]\',true); return false;" )
                                  .text(cx.variables.get('selectAll', 'multisite/lang') + " / "));
            htmlElement.append($('<a />')
                                  .attr("onclick", "changeCheckboxes(\'frmShowWebsites\',\'websites[]\',false); return false;" )
                                  .text(cx.variables.get('deSelectAll', 'multisite/lang')));
            htmlElement.append($('<input />').attr('type', 'submit').val('Update').addClass('update'));
          }
        }
      });
    }
        //Fetch multisite Website Configuration settings
        $('.multiSiteWebsiteConfig').click(function() {
            var title = $(this).data('title'),
                websiteId = $(this).attr('data-id'),
                buttons = new Object();

            cx.bind("loadingStart", cx.lock, "multiSiteWebsiteConfig");
            cx.bind("loadingEnd", cx.unlock, "multiSiteWebsiteConfig");
            cx.trigger("loadingStart", "multiSiteWebsiteConfig", {});
            cx.tools.StatusMessage.showMessage("<div id=\"loading\">" + cx.jQuery('#loading').html() + "</div>");
            domainUrl = cx.variables.get('baseUrl', 'MultiSite') + cx.variables.get('cadminPath', 'contrexx') + "index.php?cmd=JsonData&object=MultiSite&act=modifyMultisiteConfig";
            $.ajax({
                url: domainUrl,
                type: "POST",
                data: {websiteId: websiteId},
                dataType: "json",
                success: function(response) {
                    if (response.status == 'success') {
                        switch (response.data.status) {
                            case 'success':
                                var $table = $('<table />')
                                        .attr({cellspacing: "0", cellpadding: "3", border: "0", id: "MultisiteConfigTable", width: "100%"})
                                        .addClass('adminlist');
                                $html = Multisite.getConfigHtmlData(response.data.result, $table, websiteId);
                                cx.tools.StatusMessage.showMessage(response.data.message, null, 2000);
                                buttons[cx.variables.get('addNewConfig', 'multisite/lang')] = function() {
                                    Multisite.MultisiteConfig(response.data.inputTypes, "add");
                                };
                                buttons["Close"] = function() {
                                    $(this).dialog("close");

                                };
                                cx.ui.dialog({
                                    width: 820,
                                    height: 400,
                                    title: title,
                                    content: $('<div />')
                                            .attr('id', 'MultisiteConfigDiv')
                                            .append($J('<div /> ')
                                                    .addClass('alertbox')
                                                    .html(cx.variables.get('configAlertMessage', 'multisite/lang'))
                                                    )
                                            .append($html
                                                    .after('<div id ="MultisiteConfigload-lock"></div>')
                                                    ),
                                    autoOpen: true,
                                    modal: true,
                                    buttons: buttons,
                                    close: function() {
                                        $('#MultisiteConfigTable').remove();
                                    }
                                });
                                $J("#MultisiteConfigload-lock").css('height', $J("#MultisiteConfigTable").innerHeight());
                                break;
                            case 'error':
                                cx.tools.StatusMessage.showMessage(response.data.message, null, 4000);
                                break;
                            default:
                                break;
                        }
                    } else {
                        cx.tools.StatusMessage.showMessage(response.message, null, 4000);
                    }
                    cx.trigger("loadingEnd", "multiSiteWebsiteConfig", {});
                }
            });
        });
        
        /**
         * Execute the queued Sql Query in corresponding website
         */
        cx.multisite.executeSql = function(randomKey) {
            if ($('.stopexecutionQuery.ui-button').is(':visible')) {
                cx.multisite.MultisiteAjaxCall(
                        cx.variables.get('baseUrl', 'MultiSite') + cx.variables.get('cadminPath', 'contrexx') + 'index.php?cmd=JsonData&object=MultiSite&act=executeQueryBySession',
                        {randomKey: randomKey},
                        cx.multisite.callbackExecuteSqlQueryBySession
                );
            } else {
                cx.multisite.MultisiteAjaxCall(
                        cx.variables.get('baseUrl', 'MultiSite') + cx.variables.get('cadminPath', 'contrexx') + "index.php?cmd=JsonData&object=MultiSite&act=stopQueryExecution",
                        {sessionRandomKey: randomKey}
                );
            } 
        };
    });
})(jQuery);


function triggerWebsiteUpdate(domainUrl, formData) {
  cx.bind("loadingStart", cx.lock, "triggerWebsiteUpdate");
  cx.bind("loadingEnd", cx.unlock, "triggerWebsiteUpdate");
  $J.ajax({
    url: domainUrl,
    type: "POST",
    data: formData,
    dataType: "json",
    beforeSend: function () {
          cx.trigger("loadingStart", "triggerWebsiteUpdate", {});
          cx.tools.StatusMessage.showMessage("<div id=\"loading\">" + cx.jQuery('#loading').html() + "</div>");
          $J('#loading > span').html(cx.variables.get('triggeringWebsiteUpdate', 'multisite/lang'));
        },
    success: function (response) {
      cx.tools.StatusMessage.showMessage(response.data.message, null, 2000);
      cx.trigger("loadingEnd", "triggerWebsiteUpdate", {});
      $J('select[name="codeBase"]').trigger('change');
    }
  });
}

var Multisite = {
    availableLanguages : [],
    //Edit License data
    editLicense: function($this) {
        var fieldLabel = $this.data('field'),
                title = $this.attr('title'),
                websiteId = $this.data('websiteid'),
                editType = $this.data('editType'),
                licenseArray = ['licenseMessage', 'dashboardMessages', 'licenseGrayzoneMessages'],
                liceneseTable = $J('<div />');
        var tableFormat = '<table  cellspacing="0" cellpadding="3" border="0" class="adminlist licenceEdit" width="100%">';
        $form = $J('<form>')
                .addClass('saveLicense');
        if ($J.inArray(fieldLabel, licenseArray) !== -1) {
            var jsonString = $J('#ui_' + fieldLabel).html(),
                uiDiv = $J('<div>')
                            .addClass('tab-' + fieldLabel)
                            .attr('id', 'tab_menu'),
                uiTab = $J('<ul />'),
                i = 1;
            $J.each(JSON.parse(jsonString), function(index, data) {
                if (typeof data === 'object') {
                    $J('<li>')
                        .append(
                            $J('<a>')
                                .attr('href', '#tabs-' + i)
                                .html(data.lang_name)
                        )
                        .appendTo(uiTab);
                    $uiTable = ($J(tableFormat));
                    $tr = $J('<tr />');
                    $J('<td>')
                        .html(
                            $J('<label>').html(fieldLabel)
                        )
                        .appendTo($tr);
                        editFieldName = 'licenseValue['+data.lang_id+'][text]';
                    $J('<td>')
                        .html(                      
                            getEditOption(editType, editFieldName, fieldLabel, data.message, $this.data('options')) 
                        )
                        .appendTo($tr);
                    $uiTable.append($tr);
                    $uiTable.append(
                    $J('<input type="hidden">')
                       .attr('name','licenseLabel')
                       .val(fieldLabel)
                       );
                    
                    $J('<div>')
                        .attr('id', 'tabs-' + i)
                        .data('langId', data.lang_id)
                        .data('langName', data.lang_name)
                        .append($uiTable)
                        .appendTo(uiDiv);
                    i++;
                }
            });
            uiTab.prependTo(uiDiv);
            uiDiv.appendTo($form);
        } else {
            $uiTable = $J(tableFormat);
            $tr = $J('<tr />');
            $J('<td>')
                .html(
                    $J('<label>').html(fieldLabel)
                )
                .appendTo($tr);
            $J('<td>')
                    .html(getEditOption(editType, 'licenseValue', fieldLabel, $this.data('value'),$this.data('options')))
                    .appendTo($tr);
            $uiTable.append($tr);
            $uiTable.append(
                    $J('<input type="hidden">')
                       .attr('name','licenseLabel')
                       .val(fieldLabel)
                       );
            $uiTable.appendTo($form);
        }
        $form.appendTo(liceneseTable);
        domainUrl = cx.variables.get('baseUrl', 'MultiSite') + cx.variables.get('cadminPath', 'contrexx') + "index.php?cmd=JsonData&object=MultiSite&act=editLicense";
        cx.ui.dialog({
            width: 500,
            height: 250,
            title: title,
            content: liceneseTable,
            autoOpen: true,
            modal: true,
            buttons: {
                "Save": function() {
                    cx.tools.StatusMessage.showMessage("<div id=\"loading\">" + cx.jQuery('#loading').html() + "</div>");
                        var formValues = $J( "form.saveLicense" ).serialize();
                    $J.ajax({
                        url: domainUrl,
                        type: "POST",
                        data: formValues +"&websiteId="+ websiteId,
                        dataType: "json",
                        success: function(response) {
                            if (response.status == 'success') {
                                switch (response.data.status) {
                                    case 'success':
                                        if (typeof response.data.data === 'object') {
                                            var uiTabContent = [];
                                            $J.each(response.data.data, function(key, data) {
                                                $J('#' + fieldLabel).find('span.ui_licenseMsg.langId_' + key).text(data.text);
                                                uiTabContent.push({lang_id: key, lang_name: Multisite.availableLanguages[key], message: data.text});
                                            });
                                            $J('#ui_' + fieldLabel).html(JSON.stringify(uiTabContent));
                                        } else {
                                            $J('#' + fieldLabel + ' span').text(response.data.data);
                                            $J('.editLicense_' + fieldLabel).data('value', response.data.data);
                                        }
                                        cx.tools.StatusMessage.showMessage(response.data.message, null, 2000);
                                        break;
                                    case 'error':
                                        cx.tools.StatusMessage.showMessage(response.data.message, null, 4000);
                                        break;
                                    default:
                                        break;

                                }
                            } else {
                                cx.tools.StatusMessage.showMessage(response.message, null, 4000);
                            }
                        }
                    });
                    $J('#editLicense').remove();
                    $J('#tab_menu').remove();
                    $J(this).dialog("close");
                },
                "Cancel": function() {
                    $J('#editLicense').remove();
                    $J('#tab_menu').remove();
                    $J(this).dialog("close");
                }
            },
            close: function() {
                $J('#editLicense').remove();
                $J('#tab_menu').remove();
            }
        });
        $J('#tab_menu').tabs();
    },
    
    //Add/Edit/Delete Multisite Configuration Option
    MultisiteConfig: function($data,$operation) {
            var table = $J('<table />')
                            .attr({cellspacing: "0", cellpadding: "3", border: "0", id: $operation+"MultisiteConfig",width: "100%"})
                            .addClass('adminlist'),
                tr   = $J('<tr />'),
                td   = $J('<td />'),
                width = 450,
                websiteId = $J('.editMultisiteConfig').data('websiteId'),
                title = '';
        domainUrl = cx.variables.get('baseUrl', 'MultiSite') + cx.variables.get('cadminPath', 'contrexx') + "index.php?cmd=JsonData&object=MultiSite&act=modifyMultisiteConfig";
        
        switch($operation) {
            case "add":
                var selectOptions = [],
                    title = cx.variables.get('addNewConfigTitle', 'multisite/lang'),
                    width       = 520,
                    $selectBox = $J('<select />')
                                    .attr({name: "addConfigType", id: "configType"})
                                    .addClass('configType'),
                    $row2      = $J('<tr />'),
                    $row3      = $J('<tr />');
            
                $J.each($data,function(key,values){
                    selectOptions.push(values);
                });
                
                $J('<td />')
                        .html('<strong>Name</strong>')
                        .appendTo(tr);
                td.append(getEditOption('text', 'configName', 'configName', '', ''));
                td.appendTo(tr);
                
                $J('<td />')
                    .html('<strong>Type</strong>')
                    .appendTo($row2);
                    
                $J.each(selectOptions, function(key, data) {
                    $selectBox.append($J('<option />')
                                .attr('value',data)
                                .html(data));
                });
                
                $J('<td />').append($selectBox)
                            .appendTo($row2);
                $J('<td />').html('<strong>Value</strong>')
                            .appendTo($row3);
                $J('<td />')
                      .append(getEditOption('text', 'configValue', 'configValue', '', ''))
                      .appendTo($row3);
                table.append(tr);
                table.append($row2);
                table.append($row3);
                break;
            case "edit":
                var configOption = $data.data('field'),
                    title = $data.attr('title'),
                    configData = JSON.parse($J("#editMultisiteConfig_" + configOption).text()),
                    configValues = (configData.values) ? configData.values.replace(/\s+/g, '').split(',') : '',
                    configType   = configData.type,
                    configGroup = configData.group;
            
                $J('<td />')
                        .html('<strong>' + configOption + '</strong>')
                        .appendTo(tr);
                
                $J('<td />').append(getEditOption(configData.type, configOption, 'editConfig_'+configOption, configData.value, configValues))
                            .appendTo(tr);
                    
                table.append(tr);
                break;
            case "delete":
                if(confirm(cx.variables.get('deleteConfirm', 'multisite/lang'))) {
                    cx.bind("loadingStart", cx.lock, "multisiteConfigWebsite_".$operation);
                    cx.bind("loadingEnd", cx.unlock, "multisiteConfigWebsite_".$operation);
                    cx.trigger("loadingStart", "multisiteConfigWebsite_".$operation, {});
                    cx.tools.StatusMessage.showMessage("<div id=\"loading\">" + cx.jQuery('#loading').html() + "</div>");
                    $J.ajax({
                        url: domainUrl,
                        type: "POST",
                        data: {configGroup: $data.data('group'), configOption: $data.data('field'),websiteId: $data.data('websiteId'),operation: $operation},
                        dataType: "json",
                        success: function(response) {
                            if (response.status == 'success') {
                                switch (response.data.status) {
                                    case 'success':
                                        $data.closest('tr').remove();
                                        cx.tools.StatusMessage.showMessage(response.data.message, null, 2000);
                                        break;
                                    case 'error':
                                        cx.tools.StatusMessage.showMessage(response.data.message, null, 4000);
                                        break;
                                    default:
                                        break;

                                }
                            } else {
                                cx.tools.StatusMessage.showMessage(response.message, null, 4000);
                            }
                            cx.trigger("loadingEnd", "multisiteConfigWebsite_".$operation, {});
                        }
                    });
                }
                return false;
                break;
            default:
                break;
        }
        cx.ui.dialog({
            width: width,
            height: 200,
            title: title,
            content: $J('<form />')
                        .append(table),
            autoOpen: true,
            modal: true,
            buttons: {
                "Save": function() {
                    var configValue = '',
                       configNewArray = [];
               
                    if (configType == 'radio') {
                        configValue = $J('input:radio[name='+configOption+']:checked').val();
                    } else {
                        configValue = $J('.editConfig_'+configOption).val();
                    }
                    
                    if ($operation == 'add') {
                        configOption = $J(".configName").val();
                        configValue  = $J(".configValue").val();
                        configValues = $J(".configValues").val();
                        configType   = $J(".configType").val();
                        configGroup  = 'website';
                        configNewArray.push({name: configOption,section: "Multisite",group: configGroup,value: configValue,type:configType,values:configValues});
                    }
                    cx.bind("loadingStart", cx.lock, "multisiteConfigWebsite".$operation);
                    cx.bind("loadingEnd", cx.unlock, "multisiteConfigWebsite".$operation);
                    cx.trigger("loadingStart", "multisiteConfigWebsite".$operation, {});
                    cx.tools.StatusMessage.showMessage("<div id=\"loading\">" + cx.jQuery('#loading').html() + "</div>");
                    $J.ajax({
                        url: domainUrl,
                        type: "POST",
                        data: {configGroup: configGroup, configOption: configOption, configValue: configValue, websiteId: websiteId, configType: configType , configValues: configValues, operation: $operation},
                        dataType: "json",
                        success: function(response) {
                            if (response.status == 'success') {
                                switch (response.data.status) {
                                    case 'success':
                                        if ($operation == 'edit') {
                                            configData.value = configValue;
                                            $J('span#' + configOption).text(configValue);
                                            $J('span#editMultisiteConfig_' + configOption).text(JSON.stringify(configData));
                                        } else {
                                            Multisite.getConfigHtmlData(configNewArray, $J("#MultisiteConfigTable"), websiteId);
                                        }
                                        cx.tools.StatusMessage.showMessage(response.data.message, null, 2000);
                                        break;
                                    case 'error':
                                        cx.tools.StatusMessage.showMessage(response.data.message, null, 4000);
                                        break;
                                    default:
                                        break;
                                }
                            } else {
                                cx.tools.StatusMessage.showMessage(response.message, null, 4000);
                            }
                            cx.trigger("loadingEnd", "multisiteConfigWebsite".$operation, {});
                        }
                    });
                    $J('#'+$operation+'MultisiteConfig').remove();
                    $J(this).dialog("close");
                },
                "Cancel": function() {
                    $J('#'+$operation+'MultisiteConfig').remove();
                    $J(this).dialog("close");
                }
            },
            close: function() {
                $J('#'+$operation+'MultisiteConfig').remove();
            }
        });
        
        $J(".configType").change(function(){
            var tr = $J('<tr />'),
                td = $J('<td />'),
                inputBox = $J(this).val() == 'textarea' ? getEditOption('textarea', 'configValue', 'configValue', '', '') :
                                                       getEditOption('text', 'configValue', 'configValue', '', '');
                $J('.selectOptions').remove();
            switch($J(this).val()) {
                case 'textarea':
                    var textarea = $J(".configValue").closest('td');
                    $J('.configValue').remove();
                    textarea.html(inputBox);
                    break;
                case 'radio':
                case 'dropdown':
                    if ($J(".configValue").length) {
                        $J(".configValue").remove();
                        $J(this).closest('tr')
                                .next('tr')
                                .find('td:last-child')
                                .append(inputBox);
                    }
                    $J('<td />')
                            .html('<strong>Option Values</strong>')
                            .appendTo(tr);
                    
                    td.append(getEditOption('text', 'configValues', 'configValues', '', ''));
                    
                    $J('<span />')
                            .addClass('icon-info tooltip-trigger')
                            .appendTo(td);
                    $J('<span />')
                            .addClass('tooltip-message')
                            .html(cx.variables.get('configOptionTooltip', "multisite/lang"))
                            .appendTo(td);
                    tr.append(td)
                      .addClass("selectOptions");
                    $J(this).closest("tr")
                            .after(tr);
                    break;
                case 'password':
                case 'text':
                    if ($J(".configValue").length) {
                        $J(".configValue").remove();
                        $J(this).closest('tr')
                                .next('tr')
                                .find('td:last-child')
                                .append(inputBox);
                    }
                default:
                    break;
                    
            }
            cx.ui.tooltip();
        })
    },
    getConfigHtmlData:function($arrayData,$table,$websiteId) {
        $J.each($arrayData, function(key, data) {
            var tr = $J('<tr />'),
                container = $J('<span />');

            $J('<td />')
                    .html('<strong>' + data.name + '</strong>')
                    .appendTo(tr);

            $J('<span />')
                    .attr('id', data.name)
                    .html(data.value)
                    .appendTo(container);

            $J('<a href="javascript:void(0);" />')
                    .addClass('editMultisiteConfig ' + data.name)
                    .attr('title', 'Edit Multisite Configuration of ' + data.name)
                    .data({field: data.name, websiteId: $websiteId, group: data.group})
                    .click(function() {
                        Multisite.MultisiteConfig($J(this), "edit");
                    })
                    .appendTo(container);
            $J('<a href="javascript:void(0);" />')
                    .addClass('deleteMultisiteConfig ' + data.name)
                    .attr('title', 'Delete Multisite Configuration of ' + data.name)
                    .data({field: data.name, websiteId: $websiteId, group: data.group})
                    .click(function() {
                        Multisite.MultisiteConfig($J(this), "delete");
                    }).appendTo(container);


            var td = $J('<td />')
                        .append(container);
            $J('<span />')
                    .attr('id', 'editMultisiteConfig_' + data.name)
                    .html(JSON.stringify(data))
                    .hide()
                    .appendTo(td);

            tr.append(td);
                $table.append(tr);
        });
        return $table;
    },
    
    websiteRestore: function (data, upload, userId) {
        Multisite.showUserOrSubscriptionSelection(userId);
        
        // check availability of the website name
        $J('#restore_websiteName #restoreWebsiteName').bind('change', Multisite.checkWebsiteNameOnRestore);
        $J('#userSelection .selectUserType').bind('change', Multisite.validateInputOnRestore);
                
        var buttons = [
            {
                text: cx.variables.get('websiteRestoreButton', 'multisite/lang'),
                class: 'websiteRestoreButton',
                click: function () {
                    Multisite.validateInputOnRestore('#restoreWebsite .selectUserType', '#userSelection', 'validateUserSelection');
                    Multisite.validateInputOnRestore('#restore_websiteName .restoreWebsiteName', '#restore_websiteName', 'validateWebsiteName');

                    if (   $J('#restoreWebsiteForm').attr('validUserSelection') == 'false'
                        || $J('#restoreWebsiteForm').attr('websiteNameValid') == 'false'
                    ) {
                        $J('.websiteRestoreButton').attr('disabled', true);
                        $J('#restoreWebsite #restoreform_error').show();
                        return false;
                    }
                    
                    $J('.websiteRestoreButton').attr('disabled', false);
                    $J('#restoreWebsite #restoreform_error').hide();
                    if (!confirm(cx.variables.get('websiteRestoreConfirm', 'multisite/lang'))) {
                        return false;
                    }
                    
                    var selectedUserId  = $J("input:radio[name='selectUserType']:checked").val() == '2'
                                          ? $J('#restoreUserId').val()
                                          : 0;
                    var subscriptionId  = ($J('#subscriptionList .subscriptionOptions').length != 0) && $J("input:radio[name='subscription']:checked").val() == '2'
                                          ? $J('#subscriptionList .subscriptionOptions').val()
                                          : 0;
                    var params = {
                                    uploadedFilePath: upload ? data.uploadedFilePath : '',
                                    backupedServiceServer: !upload ? data.backupedServiceServer : '',
                                    websiteBackupFileName: !upload ? data.websiteBackupFileName : '',
                                    restoreOnServiceServer: $J('#restoreWebsite .serviceServer').val(),
                                    restoreWebsiteName: $J('#restoreWebsite #restoreWebsiteName').val(),
                                    responseType: 'json',
                                    selectedUserId: selectedUserId,
                                    subscriptionId: subscriptionId
                                };

                    cx.bind("loadingStart", cx.lock, "websiteRestore");
                    cx.bind("loadingEnd", cx.unlock, "websiteRestore");
                    cx.trigger("loadingStart", "websiteRestore", {});
                    $J.ajax({
                        url: cx.variables.get('cadminPath', 'contrexx') + "?cmd=JsonData&object=MultiSite&act=triggerWebsiteRestore",
                        data: params,
                        type: "POST",
                        dataType: "json",
                        beforeSend: function () {
                                $J('#restoreWebsite').dialog("close");
                                cx.tools.StatusMessage.showMessage("<div id=\"loading\" class = \"websiteBackup\">" + cx.jQuery('#loading').html() + "</div>");
                                $J('#loading > span').html(cx.variables.get('websiteRestoreInProgress', 'multisite/lang'));
                        },
                        success: function (response) {
                            var $resp = (response.data) ? response.data : response,
                                $message = ($resp.message) ? $resp.message : $resp;
                                if (typeof($resp.websiteUrl) != "undefined" && $resp.websiteUrl !== null) {
                                    window.open($resp.websiteUrl, '_blank').focus();
                                }
                                cx.tools.StatusMessage.showMessage($message, null, 2000);
                                cx.trigger("loadingEnd", "websiteRestore", {});
                            }
                    });
                }
            },
            {
                text: cx.variables.get('websiteRestoreCancelButton', 'multisite/lang'),
                click: function () {
                    $J(this).dialog("close");
                }
            }
        ];
                
        $J('#restoreWebsite').dialog({
            width: 650,
            height: 350,
            autoOpen: true,
            modal: true,
            buttons: buttons,
            close: function () {
                $J(this).dialog("destroy");
                Multisite.resetModalValuesOnRestore($J(this));
            }
        });
    },
    
    resetModalValuesOnRestore: function($element) {
        $J('#restoreWebsite .serviceServer').val('');
        $J('#restoreWebsite #restoreWebsiteName').val('');
        $J('#restoreWebsite #restoreform_error').hide();
        $element.find('.restore_error').html('').hide();
        $element.find("*").removeClass("border-red");
        $element.find("form#restoreWebsiteForm").removeAttr("validuserselection");
        $element.find("form#restoreWebsiteForm").removeAttr("websitenamevalid");
    },
    
    checkWebsiteNameOnRestore: function() {
        var $restoreForm = $J('#restoreWebsiteForm');
        var errElement = $J('#restore_websiteName').find('.restore_error');
        $J('.websiteRestoreButton').attr('disabled', true);
        jQuery.ajax({
            dataType: "json",
            url: cx.variables.get('cadminPath', 'contrexx') + "?cmd=JsonData&object=MultiSite&act=address",
            data: {multisite_address : $J(this).val()},
            type: "POST",
            success: function (response) {
                var errorMessage = (response.message.message)
                                   ? response.message.message
                                   : '';
                Multisite.parseErrorMessageOnRestore($restoreForm, 'websiteNameValid', errElement, '.restoreWebsiteName', errorMessage, !errorMessage);

                if(!errorMessage) {
                    $J('.websiteRestoreButton').attr('disabled', false).trigger('click');
                }
            }
        });
    },
    
    parseErrorMessageOnRestore: function(form, errorAttr, errElem, field, errorMsg, valid) {
        (valid) ? form.attr(errorAttr, true) : form.attr(errorAttr, false);
        (valid) ? errElem.html('').hide() : errElem.html(errorMsg).show();
        (valid) ? $J(field).removeClass('border-red') : $J(field).addClass('border-red');
    },
    
    validateInputOnRestore: function($this, error_block, inputType) {
        $this = ($this) ? $this : $J(this);
        error_block = (error_block) ? error_block : '#userSelection';
        
        var $restoreForm = $J('#restoreWebsiteForm');
        if (   inputType == 'validateWebsiteName' 
            && $restoreForm.attr('websiteNameValid') == 'false'
        ) {
            return false;
        }
        
        var errElement   = $J(error_block).find('.restore_error'),
            errorMessage = (inputType == 'validateWebsiteName')
                           ? cx.variables.get('websiteNameRequired', 'multisite/lang')
                           : cx.variables.get('websiteUserRequired', 'multisite/lang'),
            errorAttr    = (inputType == 'validateWebsiteName') 
                           ? 'websiteNameValid'
                           : 'validUserSelection',
            valid         = (inputType == 'validateWebsiteName')
                            ? !$J($this+ '#restoreWebsiteName').val().match(/^[a-z0-9]+$/)
                            : ($J($this+":checked").val() == '2') && $J('#restoreUserId').val() == 0;
        Multisite.parseErrorMessageOnRestore($restoreForm, errorAttr, errElement, $this, errorMessage, !valid);
    },
    
    showUserOrSubscriptionSelection: function(userId, selectedType) {
        var userFromBackupObj = $J("#createUserFromBackup");
        switch (selectedType) {
            case 'subscriptionOption':
                $J("input:radio[name='subscription']:checked").val() == '2'
                ? $J('#subscriptionSelection').show() 
                : $J('#subscriptionSelection').hide();
                break;
            default:
                $J('.live-search-user-clear').trigger('click');
                $J('.live-search-user-add').hide();
                if (userFromBackupObj.parent('label').is(':hidden')) {
                    userFromBackupObj.attr('checked', true).parent('label').show();
                }

                if (userId != 0 && userId != null) {
                    userFromBackupObj.attr('checked', false).parent('label').hide();
                    $J("#selectUserFromOther").attr('checked', true);
                }

                if ($J("input:radio[name='selectUserType']:checked").val() == '2') {
                    $J('.live-search-user-add').show();
                }
                break;
        }
    },
    
    showSubscriptionSelection: function(objUser) {
        var useExistingSubscription = $J('#useExistingSubscription').parent('label');
        $J('#createNewSubscription').attr('checked', true);
        $J('#subscriptionSelection').hide();
        $J('#userSelection').find('.restore_error').hide();
        $J('.websiteRestoreButton').attr('disabled', false);
        $J.ajax({
            url: cx.variables.get('cadminPath', 'contrexx') + "?cmd=JsonData&object=MultiSite&act=getAvailableSubscriptionsByUserId",
            data: {userId: objUser.id},
            type: "POST",
            dataType: "json",
            success: function (response) {
                $J('#SubscriptionOption').show();
                var $resp = (response.data) ? response.data : response;
                useExistingSubscription.hide();
                if ($resp.subscriptionsList != undefined && $resp.status == 'success') {
                    useExistingSubscription.show();
                    $J('#subscriptionSelection #subscriptionList')
                        .html('')
                        .append(getEditOption('dropdown', 'subscription', 'subscriptionOptions', '', $resp.subscriptionsList));
                }
            }
        });
    }
};

/**
 * Generate user input area for the given type 
 */
function getEditOption(type, name, fieldLabel, editValue, editOptions) {
     switch (type) {
        case 'radio':
            htmlResult = $J('<div />');
            $J.each(editOptions, function(key, value) {
                valueAndLabel = value.split(':');
                if (valueAndLabel.length < 2) {
                    valueAndLabel['0'] = key;
                    valueAndLabel['1'] = value;
                }
                radio = $J('<input type="radio"/>')
                        .addClass(fieldLabel)
                        .attr('name', name)
                        .val(valueAndLabel['0']);
                if (valueAndLabel['0'] == editValue) {
                    radio.attr('checked', 'checked');
                }
                label = $J('<label>').append(radio)
                            .append(valueAndLabel['1']+'&nbsp;');

                htmlResult.append(label);
            });
            break;

        case 'textarea':
        case 'wysiwyg':
            htmlResult = $J('<textarea rows="4" cols="40">')
                            .addClass(fieldLabel)
                            .attr('name', name)
                            .html(editValue);
            break;

        case 'dropdown':
            htmlResult = $J('<select />')
                            .addClass(fieldLabel)
                            .attr('name', name);
            $J.each(editOptions, function(key, value) {
                valueAndLabel = value.split(':');
                
                if (valueAndLabel.length < 2) {
                    valueAndLabel['0'] = key;
                    valueAndLabel['1'] = value;
                }
                
                Options = $J('<option />')
                                .attr("value", valueAndLabel['0'])
                                .text(valueAndLabel['1'])
                if (valueAndLabel['0'] == editValue) {
                    Options.attr('selected', 'selected');
                }
                htmlResult.append(Options);
            });
            
            break;

        case 'checkbox':
            htmlResult = $J('<div />');
            $J.each(editOptions, function(key, value) {
                valueAndLabel = value.split(':');
                isChecked = valueAndLabel['0'] == editValue ? 'checked' : '';
                checkbox = $J('<input type="checkbox"/>')
                                .addClass(fieldLabel)
                                .attr('name', name)
                                .val(valueAndLabel['0']);
                if (valueAndLabel['0'] == editValue) {
                    checkbox.attr('checked', 'checked');
                }
                label = $J('<label>').html(valueAndLabel['1']);

                htmlResult.append(checkbox).append(label);

            });

            break;
        case 'password':
        htmlResult = $J('<input type="password"/>')
                    .addClass(fieldLabel)
                    .attr('name', name)
                    .val(editValue);
            break;
        case 'date':
        case 'datetime':
            userInputField = $J('<input type="text"/>')
                    .addClass(fieldLabel)
                    .attr('name', name)
                    .val(editValue)
                    .attr('tabindex', -1);
            if (type == 'date') {
                htmlResult = userInputField.datepicker({defaultDate:editValue});
            }
            if (type == 'datetime') {
                htmlResult = userInputField.datetimepicker({defaultDate:editValue});
            }
            break;
        case 'email':
        case 'text':
        default:
            htmlResult = $J('<input type="text"/>')
                    .addClass(fieldLabel)
                    .attr('name', name)
                    .val(editValue);
            break;

    }
    return htmlResult;
}

cx.multisite = function() {
    return true;
};