$(document).ready(function() {
	$('#form-sign_in').ajaxForm({
		dataType: 'json',
		beforeSerialize: function() {
			//var pwd = $('#inputAppPasswordHelp').value();
			//if (pwd.length > 0) {
			//	$('#inputAppPasswordHelp').value();
			//}
		},
		beforeSubmit: function(formData, jqForm, options) {
		},
		success: function(response, statusText, xhr, $form) {
			window.location.assign('/?action=admincp'); 
		},
		error: function(response, status, error, $form) {
			if (response.status == 400) {
				$('#passwordFieldWrapper').addClass('has-error');
				$('#inputAppPasswordHelp').text(response.responseJSON.desc);
				$('#inputAppPasswordHelp').removeClass('invisible');
				$('#signInButton').attr('disabled');
			} else {
				$('#inputAppPasswordHelp').text(response.statusText);
			}
		}
	});
	
	$('#inputAppPassword').focus(function(){
		$('#passwordFieldWrapper').removeClass('has-error');
		$('#inputAppPasswordHelp').addClass('invisible');
		$('#signInButton').removeAttr('disabled');
	});
	
});
