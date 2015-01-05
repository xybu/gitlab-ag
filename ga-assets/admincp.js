/**
 * JavaScript for Control Panel.
 */

// heartbeating rate in ms
var heartbeat_rate = 5 * 60 * 1000;
// heartbeating will be enabled when performing time-consuming tasks
var enable_heartbeat = false;
// list of local storage variables
var storage_variables = ['GITLAB_AG_USER_LIST'];

$(document).ready(function() {
	
	var gitlab_url = get_gitlab_api_url();
	var gitlab_token = $('#GitLabPrivateToken').val();
		
	// set up heartbeating to prevent log-off
	window.setInterval(session_heartbeat, heartbeat_rate);
	// prevent new user form step 1 submit
	$('#formNewUser').submit(function() {return false;});
	// show alert if local storage is not enabled
	if (!store.enabled) {
		$('#global-notifications').append(new_danger_div('Local storage is not supported by the browser. Some features cannot work properly.')).removeClass('hide');
	}
	// intialize nav tab
	$('.tab-control').click(function(e) {
		e.preventDefault();
		$(this).tab('show');
	}).on('show.bs.tab', function(e) {
		$('.breadcrumb .active').remove();
		$('.breadcrumb').append('<li class="active">' + $(this).text() + '</a></li>');
		$('#LoadUserListFailureAlert').remove();
	});
	
	$('.need-load-user').on('shown.bs.tab', function(e) {
		if (get_user_list(true) == undefined) {
			var error_dom = new_danger_div('Cannot load user list from GitLab API. Please check if GitLab Url and Private Token are set properly.');
			error_dom.attr('id', 'LoadUserListFailureAlert');
			$('#global-notifications').append(error_dom).removeClass('hide');
		}
	});
	
	$('.need-load-repo').on('shown.bs.tab', function(e) {
	});
	
	// route user creation actions
	var csv_dom = $('#NewUserCsvBlock');
	csv_dom.focus(function() {
		csv_dom.parent().parent().removeClass('has-error');
		$('#CreateNewUsersResult').addClass('hide');
		$('#BtnStartCreateNewUsers').text('Start').removeClass('btn-info').removeClass('btn-success').addClass('btn-default');
	});
	$('#BtnStartCreateNewUsers').click(function() {
		var new_user_result_dom = $('#CreateNewUsersResult');
		new_user_result_dom.html('');
		new_user_result_dom.removeClass('hide');
		if (csv_dom.val().length == 0) {
			csv_dom.parent().parent().addClass('has-error');
			return false;
		}
		var csv = S(csv_dom.val()).trim().parseCSV(',', '"', '"','\n');
		if (csv.length < 2) {
			new_user_result_dom.html(new_danger_div('There is no data row in the csv.'));
			return false;
		}
		// parse the header to get the needed columns
		var username_col_num = -1;
		var name_col_num = -1;
		var email_col_num = -1;
		var uid_col_num = -1;
		var error_prompt_html = '';
		csv[0].forEach(function(val, index, parent) {
			if (val == 'NAME') name_col_num = index;
			else if (val == 'EMAIL') email_col_num = index;
			else if (val == 'User ID') username_col_num = index;
			else if (val == 'ID') uid_col_num = index;
		});
		
		if (username_col_num == -1) {
			error_prompt_html += '<li>Column for username not found. Please make sure it is marked <code>User ID</code> in the header row.</li>';
		}
		if (name_col_num == -1) {
			error_prompt_html += '<li>Column for name not found. Please make sure it is marked <code>NAME</code> in the header row.</li>';
		}
		if (email_col_num == -1) {
			error_prompt_html += '<li>Column for email not found. Please make sure it is marked <code>EMAIL</code> in the header row.</li>';
		}
		if (error_prompt_html != '') {
			new_user_result_dom.html(new_danger_div('<strong>Error: </strong><ul>' + error_prompt_html + '</ul>'));
			return false;
		}
		
		var regular_col_num = csv[0].length;
		delete csv[0];
		
		$('#BtnStartCreateNewUsers').text('Processing').removeClass('btn-default').addClass('btn-info').attr('disabled', true);
		var total_records = csv.length;
		var projects_limit = $('#inputProjectsLimit').val();
		var can_create_group = $('#Opt_CanCreateGroup').is(':checked');
		var is_admin = $('#Opt_IsAdmin').is(':checked');
		var failed_records = new Array();
		var progress_bar_dom = new_progress_bar('newUserProgressBar');
		new_user_result_dom.html(progress_bar_dom);
		enable_heartbeat = true;
		csv.forEach(function(val, i, parent) {
			var progress_txt = '';
			if (val.length != regular_col_num) {
				new_user_result_dom.append(new_danger_div('Skipped bad record: <code>' + S(val).toCSV() + '</code>'));
			} else {
				var record = {
					'email': val[email_col_num],
					'username': val[username_col_num],
					'password': new_rand_str(),
					'name': val[name_col_num]
				};
				if (uid_col_num != -1) record['extern_uid'] = val[uid_col_num];
				if (projects_limit > 0) record['projects_limit'] = projects_limit;
				if (is_admin) record['admin'] = is_admin;
				if (can_create_group) record['can_create_group'] = can_create_group;
				
				// now should call API
				$.ajax({
					url: gitlab_url + '/users?private_token=' + gitlab_token,
					async: false,
					dataType: 'json',
					type: 'POST',
					data: record,
				}).fail(function(xhr, status, errorThrown) {
					if (xhr.responseJSON.hasOwnProperty('message'))
						errorThrown = xhr.responseJSON.message;
					else errorThrown = errorThrown + ' (' + xhr.status + ')';
					new_user_result_dom.append(new_danger_div('Failed on record <code>' + S(val).toCSV() + '</code>: ' + errorThrown));
				});
				progress_txt = ': ' + record['username'];
			}
			update_progress_bar_percentage(progress_bar_dom, Math.round(100 * i / total_records), progress_txt);
			progress_bar_dom.children().removeClass('active');
		});
		// force progress bar move to 100%
		update_progress_bar_percentage(progress_bar_dom, 100, '');
		new_user_result_dom.append(new_success_div('Task complete.'));
		$('#BtnStartCreateNewUsers').text('Finished').removeAttr('disabled').removeClass('btn-info').addClass('btn-success');
		get_user_list(true);
		enable_heartbeat = false;
	});
	
	$('#BtnListMatchedUsers').click(function(e) {
		e.preventDefault();
		$('#DeleteUserResult').html('<div class="table-responsive"><table class="table table-bordered table-hover"><thead><tr><td>ID</td><td>Username</td><td>Name</td><td>Email</td><td>is_admin</td></tr></thead><tbody id="MatchUserTableBody"></tbody></table></div>');
		var user_list = store.get('GITLAB_AG_USER_LIST');
		var test_regex = wildcard_to_regex($('#inputUsernamePattern').val());
		console.log(test_regex);
		var root_username = $('#GitLabRootUsername').val();
		var show_user = $('#OptShowNormalUsers').is(':checked');
		var show_admin = $('#OptShowAdmin').is(':checked');
		enable_heartbeat = true;
		user_list.forEach(function(item, i, p) {
			if (test_regex.test(item.username) && item.username != root_username &&
			    (item.is_admin && show_admin || !(item.is_admin) && show_user)) {
				$('#MatchUserTableBody').append('<tr id="UserRow' + item.id + '"><td id="row_id">' + item.id + '</td><td id="row_username">' + item.username + '</td><td>' + item.name + '</td><td>' + item.email + '</td><td>' + item.is_admin + '</td></tr>');
			}
		});
		$('#BtnDeleteMatchedUsers').removeClass('hide');
		enable_heartbeat = false;
	});
	
	$('#BtnDeleteMatchedUsers').click(function(e) {
		e.preventDefault();
		enable_heartbeat = true;
		$('#MatchUserTableBody').children().each(function(i, obj) {
			var user_id = $(obj).find('#row_id').text();
			$.ajax({
				async: false,
				url: gitlab_url + '/users/' + user_id + '?private_token=' + gitlab_token,
				type: 'DELETE'
			}).done(function(data) {
				$(obj).remove();
			}).fail(function(xhr) {
				$(obj).addClass('danger');
			});
		});
		get_user_list(true);
		$('#DeleteUserResult').append(new_success_div('Task complete.'));
		$(this).addClass('hide');
		enable_heartbeat = false;
	});
});

function get_user_list(force) {
	if (force) store.remove('GITLAB_AG_USER_LIST');
	if (store.get('GITLAB_AG_USER_LIST') == undefined) {
		$.ajax({
			async: false,
			url: get_gitlab_api_url() + '/users?per_page=1000&private_token=' + $('#GitLabPrivateToken').val(),
			dataType: 'json'
		}).done(function(data) {
			store.set('GITLAB_AG_USER_LIST', data);
		});
	}
	return store.get('GITLAB_AG_USER_LIST');
}

function session_heartbeat() {
	if (!enable_heartbeat) return;
	$.ajax({
		type: "HEAD",
		async: true,
		url : 'admincp.php?action=heartbeat',
	});
}

function get_gitlab_api_url() {
	return $('#GitLabUrl').val() + '/api/v3';
}

function new_progress_bar(id) {
	return $('<div id="' +  id+ '" class="progress"><div class="progress-bar progress-bar-striped active" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width: 0%;">0%</div></div>');
}

function update_progress_bar_percentage(dom, p, txt) {
	var t = dom.children();
	t.attr('aria-valuenow', p);
	t.css('width', p + '%');
	t.text(p + '%' + txt);
}

function new_rand_str() {
	return (Math.random() + Math.random() + Math.random() + Math.random()).toString(36);
}

function new_danger_div(content) {
	return get_alert_div('danger', content, 'glyphicon-exclamation-sign');
}

function new_success_div(content) {
	return get_alert_div('success', content, 'glyphicon-ok-sign');
}

function get_alert_div(style, content, icon) {
	return $('<div class="alert alert-' + style + '" role="alert">' + 
				'<span class="glyphicon ' + icon + '" aria-hidden="true"></span> ' + content + '</div>');
}

function wildcard_to_regex(str) {
	str = '^' + str;
	str = str.replace('.', '\.');
	str = str.replace('*', '.*');
	str = str.replace('?', '.');
	str = str + '$';
	return new RegExp(str, 'i');
}
