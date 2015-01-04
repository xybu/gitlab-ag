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
		complete: function(xhr, status) {
			if (xhr.status == 400) {
				$('#passwordFieldWrapper').addClass('has-error');
				$('#inputAppPasswordHelp').text(xhr.responseJSON.desc);
				$('#inputAppPasswordHelp').removeClass('invisible');
				$('#signInButton').attr('disabled');
			} else if (xhr.status == 200) {
				window.location.assign('admincp.php'); 
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
