$(document).ready(function() {
	
	// Test GitLab connection event
	$('#BtnConnectToGitLab').click(function(e) {
		e.preventDefault();
		test_gitlab_conn();
	});
});

function test_gitlab_conn() {
	var gitlab_url = $('#inputGitLabUrl').val();
	var gitlab_token = $('#inputGitLabPrivateToken').val();
	var app_hook_url = $('#inputAppHookUrl').val();
	var result_dom = $('#Result');
	var alert_dom = null;
	result_dom.html('');
	if (gitlab_url == '' || gitlab_url == undefined) {
		alert_dom = new_danger_div('GitLab web url is not set.');
		alert_dom.attr('id', 'GitLabUrlNotSetAlert');
		result_dom.append(alert_dom);
	}
	if (gitlab_token == '' || gitlab_token == undefined) {
		alert_dom = new_danger_div('GitLab private token is not set.');
		alert_dom.attr('id', 'GitLabTokenNotSetAlert');
		result_dom.append(alert_dom);
	}
	if (app_hook_url == '' || app_hook_url == undefined) {
		alert_dom = new_danger_div('App site-to-set url is not set.');
		alert_dom.attr('id', 'AppS2SUrlNotSetAlert');
		result_dom.append(alert_dom);
	}
	if (alert_dom != null) return;
	
	var hook_url = app_hook_url + '/syshook/' + $('#inputGitLabHookKey').val();
	$.ajax({
		async: false,
		type: 'POST',
		url: gitlab_url + '/api/v3/hooks?private_token=' + gitlab_token,
		data: {
			'url': hook_url
		}
	}).done(function(data) {
		result_dom.append(new_success_div('Successfully added system hook to GitLab on your behalf: <code>' + hook_url + '</code>. If this URL is not accessible from GitLab server, please delete this hook on GitLab Admin panel, correct the parameters on this page, and retry. <strong>Click Install button to generate config file.</strong>'));
		$('#BtnSubmit').removeClass('hide').removeAttr('disabled');
	}).fail(function(xhr) {
		result_dom.append(new_danger_div('Failed to add system hook to GitLab on your behalf: ' + xhr.responseJSON.message + '.'));
		$('#BtnSubmit').addClass('hide');
	});
	
	$('#FormInstaller').ajaxForm({
		dataType: 'json',
		success: function(data) {
			result_dom.append(new_success_div('Installation complete! Make sure you have written down your passwords, and <a href="/">click here to sign in page.</a>'));
		},
		error: function(xhr, status, errorThrown) {
			if (xhr.responseJSON.hasOwnProperty('desc'))
				errorThrown = xhr.responseJSON.desc;
			else errorThrown = errorThrown + ' (' + xhr.status + ')';
			result_dom.append(new_danger_div(errorThrown));
		}
	});
}

function new_danger_div(content) {
	return new_alert_div('danger', content, 'glyphicon-exclamation-sign');
}

function new_success_div(content) {
	return new_alert_div('success', content, 'glyphicon-ok-sign');
}

function new_alert_div(style, content, icon) {
	return $('<div class="alert alert-' + style + '" role="alert">' + 
				'<span class="glyphicon ' + icon + '" aria-hidden="true"></span> ' + content + '</div>');
}