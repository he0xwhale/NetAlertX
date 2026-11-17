<?php
require 'php/templates/header.php';

// Pre-read default filter settings on server-side to prevent render flicker or race conditions
$configRoot = getenv('NETALERTX_CONFIG') ?: '/data/config';
$rulesPath = rtrim($configRoot, '/') . '/notification_levels.json';
$defaultFilterLevels = ["interrupt", "warning", "alert", "info"];
if (file_exists($rulesPath)) {
    $rulesJson = json_decode(file_get_contents($rulesPath), true);
    if (!empty($rulesJson['ui_filter_defaults']) && is_array($rulesJson['ui_filter_defaults'])) {
        $defaultFilterLevels = $rulesJson['ui_filter_defaults'];
    }
}
?>

<!-- iCheck -->
<link rel="stylesheet" href="lib/iCheck/all.css">
<script src="lib/iCheck/icheck.min.js"></script>

<style>
/* Ensure Bootstrap modal renders properly above any AdminLTE header/navbars */
.modal-backdrop {
  z-index: 1040 !important;
}
#levelRulesModal {
  z-index: 1050 !important;
}
#levelRulesModal .modal-dialog {
  margin-top: 65px !important;
  max-width: 850px;
}
#levelRulesModal select, 
#levelRulesModal input {
  pointer-events: auto !important;
  cursor: pointer;
}

/* Hover Popover Styles */
.report-popover {
  max-width: 550px !important;
  background: #222d32 !important;
  border: 1px solid #00c0ef !important;
  box-shadow: 0 8px 16px rgba(0,0,0,0.6) !important;
  padding: 0 !important;
}
.report-popover .popover-title {
  background: #1a2226 !important;
  color: #00c0ef !important;
  border-bottom: 1px solid #374850 !important;
  font-weight: bold;
  font-size: 13px;
  padding: 8px 12px;
}
.report-popover .popover-content {
  background: #222d32 !important;
  color: #fff !important;
  padding: 10px !important;
  font-size: 12px;
  max-height: 350px;
  overflow-y: auto;
}
.report-popover.top > .arrow:after {
  border-top-color: #00c0ef !important;
}
.report-popover.bottom > .arrow:after {
  border-bottom-color: #00c0ef !important;
}
.report-popover.left > .arrow:after {
  border-left-color: #00c0ef !important;
}
.report-popover.right > .arrow:after {
  border-right-color: #00c0ef !important;
}
</style>

<script>
  showSpinner();
</script>

<div id="notifications" class="content-wrapper">
<?php require 'php/templates/skel_notifications.php'; ?>
  <section class="content table-responsive">
    <div class="notification-box box box-gray col-xs-12" >
      <div class="box-header" style="display: flex; justify-content: space-between; align-items: center; padding: 10px 15px; flex-wrap: wrap; gap: 10px;">
        <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
          <h3 class="box-title text-aqua" style="margin: 0;"><?= lang('Notifications_All');?></h3>
          <div id="statusFilterGroup" style="display: inline-flex; align-items: center; gap: 10px; background: #1a2226; padding: 4px 10px; border-radius: 4px; border: 1px solid #374850; font-size: 12px;">
            <span style="color: #8aa4af;"><i class="fa fa-tasks"></i> 状态:</span>
            <label style="margin: 0; cursor: pointer; font-weight: normal; display: inline-flex; align-items: center; gap: 4px;">
              <input type="checkbox" class="status-cb" value="unread" checked> <span class="text-aqua">待处理</span>
            </label>
            <label style="margin: 0; cursor: pointer; font-weight: normal; display: inline-flex; align-items: center; gap: 4px;">
              <input type="checkbox" class="status-cb" value="pending" checked> <span class="text-yellow">⏳ 跟进中</span>
            </label>
            <label style="margin: 0; cursor: pointer; font-weight: normal; display: inline-flex; align-items: center; gap: 4px;">
              <input type="checkbox" class="status-cb" value="resolved"> <span class="text-muted">✅ 已处理(归档)</span>
            </label>
          </div>
        </div>
        <button type="button" id="openLevelRulesModalBtn" class="btn btn-sm btn-info" style="border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.2);">
          <i class="fa fa-sliders"></i> 通知类型级别规则设置
        </button>
      </div>
      <div class=" ">
        <table id="notificationsTable" class="table table-bordered table-hover table-striped display">
          <thead>
            <tr>
              <th><?= lang('gen_timestamp');?></th>
              <th id="th-level-header" style="position: relative; white-space: nowrap; user-select: none;">
                <span id="level-sort-trigger" title="点击排序" style="cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
                  <?= lang('gen_level');?>
                  <i class="fa fa-sort" id="level-sort-icon" style="opacity: 0.6; font-size: 11px;"></i>
                </span>
                <span id="level-filter-btn" title="点击展开级别多选" style="cursor: pointer; margin-left: 14px; padding: 3px 8px; border-radius: 4px; background: rgba(0, 192, 239, 0.2); border: 1px solid #00c0ef; display: inline-block; vertical-align: middle;">
                  <i class="fa fa-filter text-aqua" style="font-size: 11px;"></i>
                </span>
                
                <!-- Multi-select Popover -->
                <div id="levelFilterDropdown" style="display: none; position: absolute; top: 100%; left: 0; z-index: 9999; background: #222d32; border: 1px solid #4b646f; border-radius: 4px; box-shadow: 0 6px 12px rgba(0,0,0,0.5); padding: 10px; min-width: 185px; text-align: left; font-weight: normal; color: #fff;">
                  <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #374850; padding-bottom: 6px; margin-bottom: 8px;">
                    <span style="font-weight: bold; font-size: 12px;"><i class="fa fa-filter text-aqua"></i> 级别多选</span>
                    <a href="javascript:void(0)" id="resetLevelFilter" style="color: #00c0ef; font-size: 11px; text-decoration: none;">全选</a>
                  </div>
                  <div style="display: flex; flex-direction: column; gap: 6px; font-size: 12px;">
                    <label style="cursor: pointer; margin: 0; display: flex; align-items: center; gap: 6px; font-weight: normal;">
                      <input type="checkbox" class="level-cb" value="interrupt" <?= in_array('interrupt', $defaultFilterLevels) ? 'checked' : '' ?>>
                      <span class="badge bg-red" style="font-size: 10px;">interrupt</span> 严重 (Critical)
                    </label>
                    <label style="cursor: pointer; margin: 0; display: flex; align-items: center; gap: 6px; font-weight: normal;">
                      <input type="checkbox" class="level-cb" value="warning" <?= in_array('warning', $defaultFilterLevels) ? 'checked' : '' ?>>
                      <span class="badge bg-orange" style="font-size: 10px;">warning</span> 警告 (Warning)
                    </label>
                    <label style="cursor: pointer; margin: 0; display: flex; align-items: center; gap: 6px; font-weight: normal;">
                      <input type="checkbox" class="level-cb" value="alert" <?= in_array('alert', $defaultFilterLevels) ? 'checked' : '' ?>>
                      <span class="badge bg-yellow" style="font-size: 10px;">alert</span> 告警 (Alert)
                    </label>
                    <label style="cursor: pointer; margin: 0; display: flex; align-items: center; gap: 6px; font-weight: normal;">
                      <input type="checkbox" class="level-cb" value="info" <?= in_array('info', $defaultFilterLevels) ? 'checked' : '' ?>>
                      <span class="badge bg-green" style="font-size: 10px;">info</span> 信息 (Info)
                    </label>
                  </div>
                  <div style="margin-top: 10px; border-top: 1px solid #374850; padding-top: 6px; display: flex; justify-content: space-between; align-items: center;">
                    <a href="javascript:void(0)" id="saveAsDefaultFilterBtn" style="color: #f39c12; font-size: 11px; text-decoration: none;" title="将当前勾选存为打开页面的默认视图"><i class="fa fa-save"></i> 设为默认</a>
                    <button type="button" id="closeLevelDropdown" class="btn btn-xs btn-default">关闭</button>
                  </div>
                </div>
              </th>
              <th><?= lang('gen_content');?></th>
              <th>GUID</th>
              <th><?= lang('gen_read');?></th>
              <th><?= lang('gen_actions');?></th>
            </tr>
          </thead>
          <tbody>
          </tbody>
        </table>
      </div>
    </div>
  </section>
</div>

<!-- Modal Dialog placed at root body level -->
<div class="modal fade" id="levelRulesModal" tabindex="-1" role="dialog" aria-labelledby="levelRulesModalLabel">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content" style="background: #222d32; color: #fff; border: 1px solid #4b646f; border-radius: 6px; box-shadow: 0 10px 25px rgba(0,0,0,0.7);">
      <div class="modal-header" style="border-bottom: 1px solid #374850; padding: 15px 20px;">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color: #fff; opacity: 0.8;">
          <span aria-hidden="true">&times;</span>
        </button>
        <h4 class="modal-title text-aqua" id="levelRulesModalLabel"><i class="fa fa-sliders"></i> 通知类型与设备级别规则配置</h4>
      </div>
      <div class="modal-body" style="padding: 20px; max-height: 70vh; overflow-y: auto;">
        
        <!-- Default View Filter Settings -->
        <div class="box box-solid" style="background: #2c3b41; border: 1px solid #374850; margin-bottom: 20px;">
          <div class="box-header with-border" style="border-bottom: 1px solid #374850;">
            <h5 class="box-title" style="font-size: 14px; font-weight: bold; color: #00c0ef;">
              <i class="fa fa-eye"></i> 页面默认显示级别 (Default Visible Levels)
            </h5>
          </div>
          <div class="box-body" style="padding: 15px;">
            <p class="text-muted" style="color: #bbb; font-size: 12px; margin-bottom: 10px;">
              选择进入本页面时默认呈现的通知级别（例如：若日常不关心 Info，可取消勾选 Info，仅默认呈现更高告警）：
            </p>
            <div style="display: flex; gap: 20px; flex-wrap: wrap;">
              <label style="cursor: pointer; font-weight: normal; margin: 0; display: inline-flex; align-items: center; gap: 6px;">
                <input type="checkbox" id="modal_view_interrupt" value="interrupt">
                <span class="badge bg-red">interrupt</span> 严重
              </label>
              <label style="cursor: pointer; font-weight: normal; margin: 0; display: inline-flex; align-items: center; gap: 6px;">
                <input type="checkbox" id="modal_view_warning" value="warning">
                <span class="badge bg-orange">warning</span> 警告
              </label>
              <label style="cursor: pointer; font-weight: normal; margin: 0; display: inline-flex; align-items: center; gap: 6px;">
                <input type="checkbox" id="modal_view_alert" value="alert">
                <span class="badge bg-yellow">alert</span> 告警
              </label>
              <label style="cursor: pointer; font-weight: normal; margin: 0; display: inline-flex; align-items: center; gap: 6px;">
                <input type="checkbox" id="modal_view_info" value="info">
                <span class="badge bg-green">info</span> 信息
              </label>
            </div>
          </div>
        </div>

        <!-- Default Event Categories -->
        <div class="box box-solid" style="background: #2c3b41; border: 1px solid #374850; margin-bottom: 20px;">
          <div class="box-header with-border" style="border-bottom: 1px solid #374850;">
            <h5 class="box-title" style="font-size: 14px; font-weight: bold; color: #00c0ef;">
              <i class="fa fa-list"></i> 基础事件大类默认级别
            </h5>
          </div>
          <div class="box-body" style="padding: 15px;">
            <div class="row">
              <div class="col-sm-4" style="margin-bottom: 12px;">
                <label>普通通断事件 (Connected / Disconnected):</label>
                <select id="rule_def_events" class="form-control" style="background: #1a2226; color: #fff; border-color: #4b646f;">
                  <option value="info">Info (信息 / 绿色)</option>
                  <option value="alert">Alert (告警 / 黄色)</option>
                  <option value="warning">Warning (警告 / 橙色)</option>
                  <option value="interrupt">Interrupt (严重 / 红色)</option>
                </select>
                <span class="help-block text-muted" style="color: #999; font-size: 11px;">随身设备/学习机/电脑日常锁屏通断</span>
              </div>
              <div class="col-sm-4" style="margin-bottom: 12px;">
                <label>新设备发现 (New Device):</label>
                <select id="rule_def_new_devices" class="form-control" style="background: #1a2226; color: #fff; border-color: #4b646f;">
                  <option value="warning">Warning (警告 / 橙色)</option>
                  <option value="alert">Alert (告警 / 黄色)</option>
                  <option value="interrupt">Interrupt (严重 / 红色)</option>
                  <option value="info">Info (信息 / 绿色)</option>
                </select>
                <span class="help-block text-muted" style="color: #999; font-size: 11px;">未登记新MAC潜入局域网</span>
              </div>
              <div class="col-sm-4" style="margin-bottom: 12px;">
                <label>设备宕机离线 (Down):</label>
                <select id="rule_def_down_devices" class="form-control" style="background: #1a2226; color: #fff; border-color: #4b646f;">
                  <option value="interrupt">Interrupt (严重 / 红色)</option>
                  <option value="warning">Warning (警告 / 橙色)</option>
                  <option value="alert">Alert (告警 / 黄色)</option>
                  <option value="info">Info (信息 / 绿色)</option>
                </select>
                <span class="help-block text-muted" style="color: #999; font-size: 11px;">被标记监控的设备断开</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Device Specific Overrides -->
        <div class="box box-solid" style="background: #2c3b41; border: 1px solid #374850; margin-bottom: 10px;">
          <div class="box-header with-border" style="border-bottom: 1px solid #374850; display: flex; justify-content: space-between; align-items: center;">
            <h5 class="box-title" style="font-size: 14px; font-weight: bold; color: #00c0ef;">
              <i class="fa fa-server"></i> 特定设备级别自定义覆盖 (Device Overrides)
            </h5>
            <button type="button" id="addDeviceOverrideBtn" class="btn btn-xs btn-success"><i class="fa fa-plus"></i> 添加设备规则</button>
          </div>
          <div class="box-body" style="padding: 15px;">
            <p class="text-muted" style="color: #bbb; font-size: 12px; margin-bottom: 12px;">
              说明：若特定设备发生事件，优先遵循此处针对该设备制定的告警级别（如核心主路由宕机设为 Interrupt，普通学习机设为 Info）。
            </p>
            <table class="table table-bordered table-striped" id="deviceOverridesTable" style="background: #1a2226; color: #fff;">
              <thead>
                <tr>
                  <th style="width: 35%;">设备 MAC 地址</th>
                  <th style="width: 30%;">备注名称</th>
                  <th style="width: 25%;">指定通知级别</th>
                  <th style="width: 10%; text-align: center;">操作</th>
                </tr>
              </thead>
              <tbody id="deviceOverridesTbody">
              </tbody>
            </table>
          </div>
        </div>

      </div>
      <div class="modal-footer" style="border-top: 1px solid #374850; padding: 12px 20px;">
        <button type="button" class="btn btn-default" data-dismiss="modal">取消</button>
        <button type="button" id="saveLevelRulesBtn" class="btn btn-primary"><i class="fa fa-save"></i> 保存配置并应用</button>
      </div>
    </div>
  </div>
</div>

<script>
  function safeHideSkeleton() {
    try {
      $('#notifications-skeleton').fadeOut(0, function() { $(this).hide(); });
    } catch(e) {}
    try {
      if (typeof hideSpinner === 'function') hideSpinner();
    } catch(e) {}
  }

  var g_notificationsTableData = {};
  var g_levelRules = {
    default_levels: {
      events: "info",
      new_devices: "warning",
      down_devices: "interrupt"
    },
    ui_filter_defaults: <?= json_encode($defaultFilterLevels) ?>,
    device_overrides: {}
  };

  function loadLevelRules(callback) {
    $.get('php/server/query_json.php', { file: 'notification_levels.json', nocache: Date.now() })
      .done(function(data) {
        if (data && typeof data === 'object') {
          g_levelRules = $.extend(true, {}, g_levelRules, data);
        }
        if (callback) callback();
      })
      .fail(function() {
        if (callback) callback();
      });
  }

  function fetchData(callback) {
    // Parallel fetch rules, table_notifications, and user_notifications
    $.when(
      $.get('php/server/query_json.php', { file: 'notification_levels.json', nocache: Date.now() }),
      $.get('php/server/query_json.php', { file: 'table_notifications.json', nocache: Date.now() }),
      $.get('php/server/query_json.php', { file: 'user_notifications.json', nocache: Date.now() })
    ).done(function(rulesRes, tableRes, userRes) {
      if (rulesRes[0] && typeof rulesRes[0] === 'object') {
        g_levelRules = $.extend(true, {}, g_levelRules, rulesRes[0]);
      }

      var tblData = tableRes[0] ? (tableRes[0].data || tableRes[0]) : [];
      if (Array.isArray(tblData)) {
        tblData.forEach(function(row) {
          if (row.guid) {
            g_notificationsTableData[row.guid] = row;
          }
        });
      }

      var userList = userRes[0] || [];
      if (typeof userList === 'string') {
        try { userList = JSON.parse(userList); } catch(e) { userList = []; }
      }

      if (Array.isArray(userList)) {
        userList.forEach(function(item) {
          if (item.content && item.content.indexOf('Report:') !== -1) {
            var rawGuid = item.content.replace('Report:', '').split('::')[0].trim();
            if (g_notificationsTableData[rawGuid]) {
              try {
                var rep = g_notificationsTableData[rawGuid];
                var jd = typeof rep.json === 'string' ? JSON.parse(rep.json) : rep.json;
                var defs = (g_levelRules && g_levelRules.default_levels) ? g_levelRules.default_levels : { events: 'info', new_devices: 'warning', down_devices: 'interrupt' };
                var overrides = (g_levelRules && g_levelRules.device_overrides) ? g_levelRules.device_overrides : {};
                var weightMap = { 'info': 1, 'alert': 2, 'warning': 3, 'interrupt': 4 };
                var weightToLevel = { 1: 'info', 2: 'alert', 3: 'warning', 4: 'interrupt' };
                var highestWeight = 1;

                if (jd.down_devices && jd.down_devices.length > 0) {
                  var baseL = defs.down_devices || 'interrupt';
                  highestWeight = Math.max(highestWeight, weightMap[baseL] || 4);
                }
                if (jd.new_devices && jd.new_devices.length > 0) {
                  var baseL = defs.new_devices || 'warning';
                  highestWeight = Math.max(highestWeight, weightMap[baseL] || 3);
                }
                if (jd.events && jd.events.length > 0) {
                  var baseL = defs.events || 'info';
                  highestWeight = Math.max(highestWeight, weightMap[baseL] || 1);
                }

                var involved = [].concat(jd.down_devices || [], jd.new_devices || [], jd.events || []);
                involved.forEach(function(d) {
                  var mac = (d.eveMac || '').toLowerCase();
                  if (overrides[mac] && overrides[mac].level) {
                    var ovL = overrides[mac].level;
                    highestWeight = Math.max(highestWeight, weightMap[ovL] || 1);
                  }
                });

                item.level = weightToLevel[highestWeight] || 'info';
              } catch(e) {
                item.level = 'info';
              }
            }
          }
        });
      }

      callback(Array.isArray(userList) ? userList : []);
    }).fail(function() {
      $.get('php/server/query_json.php', { file: 'user_notifications.json', nocache: Date.now() })
        .done(function(res) { callback(Array.isArray(res) ? res : []); })
        .fail(function() { callback([]); });
    });
  }

  // Generate Table HTML for Hover Popover
  function buildHoverTableHtml(guid) {
    var rep = g_notificationsTableData[guid];
    if (!rep) return '<div style="color: #999; padding: 5px;">暂无扩展报表数据</div>';
    
    var jd = rep.json;
    if (typeof jd === 'string') {
      try { jd = JSON.parse(jd); } catch(e) { jd = {}; }
    }
    if (!jd || typeof jd !== 'object') return '<div style="color: #999; padding: 5px;">报表内容为空</div>';

    var down_d = jd.down_devices || [];
    var new_d = jd.new_devices || [];
    var ev_d = jd.events || [];

    if (down_d.length === 0 && new_d.length === 0 && ev_d.length === 0) {
      return '<div style="color: #bbb; padding: 5px;">该时段网络设备无离线或事件变动</div>';
    }

    var html = '<table class="table table-condensed table-bordered" style="margin-bottom: 0; background: #1a2226; color: #ecf0f5; font-size: 11px;">';
    html += '<thead><tr style="background: #111; color: #00c0ef;"><th>设备名称</th><th>IP地址</th><th>MAC地址</th><th>变动类型</th></tr></thead><tbody>';

    down_d.forEach(function(d) {
      html += '<tr><td><span style="color:#f56954;">🔴 ' + (d.devName || '未知') + '</span></td><td>' + (d.eveIp || '-') + '</td><td>' + (d.eveMac || '-') + '</td><td><span class="badge bg-red">Down</span></td></tr>';
    });

    new_d.forEach(function(d) {
      html += '<tr><td><span style="color:#00a65a;">🆕 ' + (d.devName || '新发现设备') + '</span></td><td>' + (d.eveIp || '-') + '</td><td>' + (d.eveMac || '-') + '</td><td><span class="badge bg-orange">New Device</span></td></tr>';
    });

    ev_d.forEach(function(e) {
      var badge = e.eveEventType === 'Connected' ? '<span class="badge bg-green">Connected</span>' : '<span class="badge bg-gray">Disconnected</span>';
      html += '<tr><td><span style="color:#00c0ef;">⚡ ' + (e.devName || '设备') + '</span></td><td>' + (e.eveIp || '-') + '</td><td>' + (e.eveMac || '-') + '</td><td>' + badge + '</td></tr>';
    });

    html += '</tbody></table>';
    html += '<div style="margin-top: 8px; text-align: right; color: #00c0ef;"><small>点击标题即可全屏查看官方报表详情 &rarr;</small></div>';
    return html;
  }

  $(document).ready(function() {
    updateFilterButtonState();

    const table = $('#notificationsTable').DataTable({
      "autoWidth": false,
      "pageLength": parseInt(getSetting("UI_DEFAULT_PAGE_SIZE")),
      'lengthMenu': getLengthMenu(parseInt(getSetting("UI_DEFAULT_PAGE_SIZE"))),
      "columns": [
        { 
          "data": "timestamp",
          "render": function(data, type, row) {
            var result = data.toString();
            if (result.includes("+")) {
              result = result.split('+')[0];
            }
            if (type === "sort" || type === "type" || type !== "display") {
              return result;
            }
            return '<div style="white-space: nowrap;">' + result + '</div>';
          }
        },
        { 
          "data": "level",
          "render": function(data, type, row) {
            var color = "blue";
            var weight = 1;
            switch(data) {
              case "interrupt":
                color = "red";
                weight = 4;
                break;
              case "warning":
                color = "orange";
                weight = 3;
                break;
              case "alert":
                color = "yellow";
                weight = 2;
                break;
              case "info":
                color = "green";
                weight = 1;
                break;
            }

            if (type === "sort" || type === "type") {
              return weight;
            }

            return `<span class="badge bg-${color}" style="display: inline-block; font-size: 11px;"> ${data} </span>`;
          }
        },
        { 
          "data": "content",
          "render": function(data, type, row) {
            if (data.includes("Report:")) {
              var raw = data.replace("Report:", "").trim();
              var guid = raw;
              var inlineSummary = "";
              if (raw.includes("::")) {
                var parts = raw.split("::");
                guid = parts[0].trim();
                inlineSummary = parts[1].trim();
              }
              
              var summaryText = inlineSummary;
              if (!summaryText && g_notificationsTableData[guid]) {
                var rep = g_notificationsTableData[guid];
                try {
                  var jd = typeof rep.json === 'string' ? JSON.parse(rep.json) : rep.json;
                  var parts = [];
                  if (jd.down_devices && jd.down_devices.length > 0) {
                    var names = jd.down_devices.map(function(d){ return d.devName || d.eveMac; });
                    parts.push("🔴离线: " + names.join(", "));
                  }
                  if (jd.new_devices && jd.new_devices.length > 0) {
                    var names = jd.new_devices.map(function(d){ return d.devName || d.eveMac; });
                    parts.push("🆕新设备: " + names.join(", "));
                  }
                  if (jd.events && jd.events.length > 0) {
                    var evParts = jd.events.slice(0, 2).map(function(e){ return (e.devName || e.eveMac) + " (" + e.eveEventType + ")"; });
                    if (jd.events.length > 2) evParts.push("等共" + jd.events.length + "个");
                    parts.push("⚡事件: " + evParts.join(", "));
                  }
                  if (parts.length > 0) summaryText = parts.join(" | ");
                } catch(e) {}
              }

              if (!summaryText) {
                summaryText = "查看网络状态报表详情";
              }

              var linkHtml = `<a href="report.php?guid=${guid}" class="report-link-trigger" data-guid="${guid}" style="color: #00c0ef; text-decoration: underline; font-weight: 500; font-size: 13px; display: inline-flex; align-items: center; gap: 6px;">
                                <span>${summaryText}</span>
                                <i class="fa fa-external-link" style="font-size: 10px; opacity: 0.7;"></i>
                              </a>`;

              return `<div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap;">
                        <div>${linkHtml}</div>
                        <div><a href="report.php?guid=${guid}" class="btn btn-xs btn-primary" style="margin-left: 5px;"><i class="fa fa-file-text-o"></i> 报表详情</a></div>
                      </div>`;
            } else {
              var text = (data.startsWith('"') && data.endsWith('"')) ? data.slice(1, -1) : data;
              return `<span style="color: #ecf0f5;">${text}</span>`;
            }
          }
        },
        { 
          "data": "guid",
          "render": function(data, type, row) {
            return `<button class="copy-btn btn btn-info btn-flat" data-text="${data}" title="copy" onclick="copyToClipboard(this)">
                    <i class="fa-solid fa-copy"></i>
                  </button>`;
          }
        },
        { 
          "data": "read",
          "render": function(data, type, row) {
            var st = row.status ? row.status : (data == 1 ? 'resolved' : 'unread');
            if (type === "sort" || type === "type") {
              return st;
            }
            if (st === 'unread') {
              return `<div style="display: flex; gap: 4px; white-space: nowrap;">
                        <button class="btn btn-xs btn-warning btn-flat" title="标记为跟进中（消除未读红点，保留在列表）" onclick="setNotifStatus('${row.guid}', 'pending', this)">⏳ 跟进</button>
                        <button class="btn btn-xs btn-success btn-flat" title="标记为已处理（归档隐藏）" onclick="setNotifStatus('${row.guid}', 'resolved', this)">✅ 已处理</button>
                      </div>`;
            } else if (st === 'pending') {
              return `<div style="display: flex; align-items: center; gap: 5px; white-space: nowrap;">
                        <span class="label label-warning" style="font-size: 11px;">⏳ 跟进中</span>
                        <button class="btn btn-xs btn-success btn-flat" title="完成处理并归档" onclick="setNotifStatus('${row.guid}', 'resolved', this)">✅ 完成</button>
                      </div>`;
            } else {
              return `<div style="display: flex; align-items: center; gap: 5px; white-space: nowrap;">
                        <span class="label label-default" style="font-size: 11px; background: #374850; color: #bbb;">✅ 已处理</span>
                        <button class="btn btn-xs btn-default btn-flat" title="恢复为跟进中" onclick="setNotifStatus('${row.guid}', 'pending', this)" style="background: #222d32; color: #ccc; border-color: #4b646f;"><i class="fa fa-undo"></i></button>
                      </div>`;
            }
          }
        },
        {
          targets: -1,
          data: 'guid',
          render: function(data, type, row) {
            return `<button class="mark-read-btn btn btn-danger btn-flat" onclick="removeNotification('${data}', this)">
                      <i class="fa-solid fa-trash"></i>
                    </button>`;
          }
        }
      ],
      "columnDefs": [
        { "width": "15%", "targets": [0] },
        { "width": "12%", "targets": [1] },
        { "width": "53%", "targets": [2] },
        { "width": "5%", "targets": [3,4,5] }
      ],
      "order": [[0, "desc"]],
      initComplete: function(settings, json) {
        safeHideSkeleton();
      },
      drawCallback: function() {
        safeHideSkeleton();
        // Init Hover Popover for report link titles
        try {
          // Dispose existing popovers to prevent detached duplicate instances
          $('.report-link-trigger').popover('destroy');
        } catch(e) {}
        try {
          $('.report-link-trigger').popover({
            trigger: 'hover',
            placement: 'auto top',
            html: true,
            container: 'body',
            sanitize: false,
            delay: { show: 100, hide: 150 },
            template: '<div class="popover report-popover" role="tooltip"><div class="arrow"></div><h3 class="popover-title"></h3><div class="popover-content"></div></div>',
            title: function() {
              return '<i class="fa fa-table text-aqua"></i> 报表详情快速预览';
            },
            content: function() {
              var guid = $(this).data('guid');
              return buildHoverTableHtml(guid);
            }
          });
        } catch(e) {}
      }
    });

    // Custom DataTables filter for Level and Status columns
    $.fn.dataTable.ext.search.push(
      function(settings, data, dataIndex, rowData, counter) {
        if (settings.nTable.id !== 'notificationsTable') {
          return true;
        }
        // 1. Level filter
        var selectedLevels = [];
        $('.level-cb:checked').each(function() {
          selectedLevels.push($(this).val());
        });
        if (selectedLevels.length === 0) {
          return false;
        }
        if (selectedLevels.length < 4) {
          var rowLevel = (rowData.level || '').toLowerCase();
          if (selectedLevels.indexOf(rowLevel) === -1) {
            return false;
          }
        }

        // 2. Status filter
        var selectedStatuses = [];
        $('.status-cb:checked').each(function() {
          selectedStatuses.push($(this).val());
        });
        if (selectedStatuses.length === 0) {
          return false;
        }
        var rowStatus = rowData.status ? rowData.status : (rowData.read == 1 ? 'resolved' : 'unread');
        return selectedStatuses.indexOf(rowStatus) !== -1;
      }
    );

    $('.status-cb').on('change', function() {
      table.draw();
    });

    function updateFilterButtonState() {
      var selected = [];
      $('.level-cb:checked').each(function() {
        selected.push($(this).val());
      });

      if (selected.length < 4) {
        $('#level-filter-btn').css('background', '#00c0ef').find('i').removeClass('text-aqua').css('color', '#fff');
      } else {
        $('#level-filter-btn').css('background', 'rgba(0, 192, 239, 0.2)').find('i').addClass('text-aqua').css('color', '');
      }
    }

    function applyLevelFilter() {
      updateFilterButtonState();
      table.draw();
    }

    // Toggle dropdown popover on filter button click
    $('#level-filter-btn').on('click', function(e) {
      e.stopPropagation();
      $('#levelFilterDropdown').toggle();
    });

    $('#levelFilterDropdown').on('click', function(e) {
      e.stopPropagation();
    });

    $(document).on('click', function() {
      $('#levelFilterDropdown').hide();
    });

    $('#closeLevelDropdown').on('click', function() {
      $('#levelFilterDropdown').hide();
    });

    $('#resetLevelFilter').on('click', function() {
      $('.level-cb').prop('checked', true);
      applyLevelFilter();
    });

    $('.level-cb').on('change', function() {
      applyLevelFilter();
    });

    // Save current active filter as default
    $('#saveAsDefaultFilterBtn').on('click', function() {
      var curSelected = [];
      $('.level-cb:checked').each(function() {
        curSelected.push($(this).val());
      });
      g_levelRules.ui_filter_defaults = curSelected;

      $.ajax({
        url: 'php/server/save_notification_levels.php',
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(g_levelRules),
        success: function() {
          $('#saveAsDefaultFilterBtn').html('<i class="fa fa-check text-green"></i> 已保存');
          setTimeout(function() {
            $('#saveAsDefaultFilterBtn').html('<i class="fa fa-save"></i> 设为默认');
            $('#levelFilterDropdown').hide();
          }, 1200);
        }
      });
    });

    // Initial Data Fetch and table population
    fetchData(function(data) {
      var defViews = g_levelRules.ui_filter_defaults || ["interrupt", "warning", "alert", "info"];
      $('.level-cb').each(function() {
        var val = $(this).val();
        $(this).prop('checked', defViews.indexOf(val) !== -1);
      });
      updateFilterButtonState();

      table.clear().rows.add(data).draw();
      safeHideSkeleton();
    });

    // Safety timeout to ensure skeleton/spinner always dismisses even if data is delayed
    setTimeout(safeHideSkeleton, 2000);

    // Isolated sort when clicking #level-sort-trigger
    var currentLevelSort = null;
    $('#level-sort-trigger').on('click', function(e) {
      e.stopPropagation();
      if (currentLevelSort === 'asc') {
        currentLevelSort = 'desc';
        table.order([1, 'desc']).draw();
        $('#level-sort-icon').removeClass('fa-sort fa-sort-up').addClass('fa-sort-down text-aqua').css('opacity', '1');
      } else {
        currentLevelSort = 'asc';
        table.order([1, 'asc']).draw();
        $('#level-sort-icon').removeClass('fa-sort fa-sort-down').addClass('fa-sort-up text-aqua').css('opacity', '1');
      }
    });

    // Modal logic
    $('#openLevelRulesModalBtn').on('click', function(e) {
      e.preventDefault();
      loadLevelRules(function() {
        // Populate default view filter checkboxes in modal
        var defViews = g_levelRules.ui_filter_defaults || ["interrupt", "warning", "alert", "info"];
        $('#modal_view_interrupt').prop('checked', defViews.indexOf('interrupt') !== -1);
        $('#modal_view_warning').prop('checked', defViews.indexOf('warning') !== -1);
        $('#modal_view_alert').prop('checked', defViews.indexOf('alert') !== -1);
        $('#modal_view_info').prop('checked', defViews.indexOf('info') !== -1);

        $('#rule_def_events').val((g_levelRules.default_levels && g_levelRules.default_levels.events) || 'info');
        $('#rule_def_new_devices').val((g_levelRules.default_levels && g_levelRules.default_levels.new_devices) || 'warning');
        $('#rule_def_down_devices').val((g_levelRules.default_levels && g_levelRules.default_levels.down_devices) || 'interrupt');

        var tbody = $('#deviceOverridesTbody');
        tbody.empty();
        var overrides = g_levelRules.device_overrides || {};
        for (var mac in overrides) {
          addOverrideRow(mac, overrides[mac].name || '', overrides[mac].level || 'info');
        }
        $('#levelRulesModal').modal({ backdrop: 'static', keyboard: true, show: true });
      });
    });

    function addOverrideRow(mac, name, level) {
      var row = $(`
        <tr>
          <td><input type="text" class="form-control input-sm ov-mac" value="${mac}" placeholder="aa:bb:cc:dd:ee:ff" style="background: #1a2226; color: #fff; border-color: #4b646f;"></td>
          <td><input type="text" class="form-control input-sm ov-name" value="${name}" placeholder="如 客厅-HP笔记本" style="background: #1a2226; color: #fff; border-color: #4b646f;"></td>
          <td>
            <select class="form-control input-sm ov-level" style="background: #1a2226; color: #fff; border-color: #4b646f;">
              <option value="info" ${level === 'info' ? 'selected' : ''}>Info (信息 / 绿色)</option>
              <option value="alert" ${level === 'alert' ? 'selected' : ''}>Alert (告警 / 黄色)</option>
              <option value="warning" ${level === 'warning' ? 'selected' : ''}>Warning (警告 / 橙色)</option>
              <option value="interrupt" ${level === 'interrupt' ? 'selected' : ''}>Interrupt (严重 / 红色)</option>
            </select>
          </td>
          <td style="text-align: center;">
            <button type="button" class="btn btn-xs btn-danger del-ov-btn"><i class="fa fa-trash"></i></button>
          </td>
        </tr>
      `);
      row.find('.del-ov-btn').on('click', function() {
        row.remove();
      });
      $('#deviceOverridesTbody').append(row);
    }

    $('#addDeviceOverrideBtn').on('click', function() {
      addOverrideRow('', '', 'info');
    });

    $('#saveLevelRulesBtn').on('click', function() {
      var selectedDefaults = [];
      if ($('#modal_view_interrupt').is(':checked')) selectedDefaults.push('interrupt');
      if ($('#modal_view_warning').is(':checked')) selectedDefaults.push('warning');
      if ($('#modal_view_alert').is(':checked')) selectedDefaults.push('alert');
      if ($('#modal_view_info').is(':checked')) selectedDefaults.push('info');

      var newRules = {
        default_levels: {
          events: $('#rule_def_events').val(),
          new_devices: $('#rule_def_new_devices').val(),
          down_devices: $('#rule_def_down_devices').val()
        },
        ui_filter_defaults: selectedDefaults,
        device_overrides: {}
      };

      $('#deviceOverridesTbody tr').each(function() {
        var mac = $(this).find('.ov-mac').val().trim().toLowerCase();
        var name = $(this).find('.ov-name').val().trim();
        var level = $(this).find('.ov-level').val();
        if (mac) {
          newRules.device_overrides[mac] = { name: name, level: level };
        }
      });

      $.ajax({
        url: 'php/server/save_notification_levels.php',
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(newRules),
        success: function(resp) {
          $('#levelRulesModal').modal('hide');
          g_levelRules = newRules;
          // Sync header checkboxes
          $('.level-cb').each(function() {
            var val = $(this).val();
            $(this).prop('checked', selectedDefaults.indexOf(val) !== -1);
          });
          applyLevelFilter();
          fetchData(function(data) {
            table.clear().rows.add(data).draw();
            safeHideSkeleton();
          });
        },
        error: function(err) {
          alert('保存失败，请检查网络或后端权限');
        }
      });
    });

  });

  function setNotifStatus(guid, newStatus, btn) {
    if (btn) $(btn).prop('disabled', true);
    $.ajax({
      url: 'php/server/update_notification_status.php',
      type: 'POST',
      contentType: 'application/json',
      data: JSON.stringify({ guid: guid, status: newStatus }),
      success: function(resp) {
        const table = $('#notificationsTable').DataTable();
        table.rows().every(function() {
          var d = this.data();
          if (d && d.guid === guid) {
            d.status = newStatus;
            d.read = (newStatus === 'unread') ? 0 : 1;
            this.data(d);
          }
        });
        table.draw(false);
      },
      error: function() {
        if (btn) $(btn).prop('disabled', false);
        alert('状态更新失败，请重试');
      }
    });
  }

  function renderTable(data) {
    const table = $('#notificationsTable').DataTable();
    table.clear().rows.add(data).draw();
    safeHideSkeleton();
  }
</script>

<?php require 'php/templates/footer.php'; ?>
