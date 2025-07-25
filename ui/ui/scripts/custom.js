// radio checked - hotspot plan
$(document).ready(function () {
    $('input[type=radio]').change(function(){

	if ($('#Time_Limit').is(':checked')) {
        $('#DataLimit').hide();
		$('#TimeLimit').show();
    }
	if ($('#Data_Limit').is(':checked')) {
        $('#TimeLimit').hide();
		$('#DataLimit').show();
    }
	if ($('#Both_Limit').is(':checked')) {
        $('#TimeLimit').show();
		$('#DataLimit').show();
    }

	if ($('#Unlimited').is(':checked')) {
        $('#Type').hide();
		$('#TimeLimit').hide();
		$('#DataLimit').hide();
    } else {
        $('#Type').show();
    }

	if ($('#Hotspot').is(':checked')) {
        $('#p').hide();
		$('#h').show();
    }
	if ($('#PPPOE').is(':checked')) {
        $('#p').show();
		$('#h').hide();
    }

    });
});
$("#Hotspot").prop("checked", true).change();


function checkIP(f, id) {
	if (f.value.length > 6) {
		$.get('./?_route=autoload/pppoe_ip_used&ip=' + f.value + '&id=' + id, function(data) {
			$("#warning_ip").html(data)
		});
	}
}

function checkUsername(f, id) {
	if (f.value.length > 1) {
		$.get('./?_route=autoload/pppoe_username_used&u=' + f.value + '&id=' + id, function(data) {
			$("#warning_username").html(data)
		});
	}
}

//auto load pool - pppoe plan
var htmlobjek;
$(document).ready(function(){
  $("#routers").change(function(){
    var routers = $("#routers").val();
    $.ajax({
        url: "index.php?_route=autoload/pool",
        data: "routers="+routers,
        cache: false,
        success: function(msg){
            $("#pool_name").html(msg);
        }
    });
  });
});

//auto load plans data - recharge user
$(function() {
    $('input[type=radio]').change(function(){
		if ($('#Hot').is(':checked')) {
				$.ajax({
					type: "POST",
					dataType: "html",
					url: "index.php?_route=autoload/server",
					success: function(msg){
						$("#server").html(msg);
					}
				});

				$("#server").change(getAjaxAlamat);
				function getAjaxAlamat(){
					var server = $("#server").val();
					$.ajax({
						type: "POST",
						dataType: "html",
						url: "index.php?_route=autoload/plan",
						data: "jenis=Hotspot&server="+server,
						success: function(msg){
							$("#plan").html(msg);
						}
					});
				};

		} else if ($('#POE').is(':checked')) {
				$.ajax({
					type: "POST",
					dataType: "html",
					url: "index.php?_route=autoload/server",
					success: function(msg){
						$("#server").html(msg);
					}
				});
				$("#server").change(function(){
					var server = $("#server").val();
					$.ajax({
						type: "POST",
						dataType: "html",
						url: "index.php?_route=autoload/plan",
						data: "jenis=PPPOE&server="+server,
						success: function(msg){
							$("#plan").html(msg);
						}
					});
				});
		} else {
				$.ajax({
					type: "POST",
					dataType: "html",
					url: "index.php?_route=autoload/server",
					success: function(msg){
						$("#server").html(msg);
					}
				});
				$("#server").change(function(){
					var server = $("#server").val();
					$.ajax({
						type: "POST",
						dataType: "html",
						url: "index.php?_route=autoload/plan",
						data: "jenis=VPN&server="+server,
						success: function(msg){
							$("#plan").html(msg);
						}
					});
				});
		}
    });
});


function showPrivacy() {
    $('#HTMLModal_title').html('Privacy Policy');
    $('#HTMLModal_konten').html('<center><img src="ui/ui/images/loading.gif"></center>');
    $('#HTMLModal').modal({
        'show': true,
        'backdrop': false,
    });
    $.get('pages/Privacy_Policy.html?' + (new Date()), function(data) {
        $('#HTMLModal_konten').html(data);
    });
}

function showTaC() {
    $('#HTMLModal_title').html('Terms and Conditions');
    $('#HTMLModal_konten').html('<center><img src="ui/ui/images/loading.gif"></center>');
    $('#HTMLModal').modal({
        'show': true,
        'backdrop': false,
    });
    $.get('pages/Terms_and_Conditions.html?' + (new Date()), function(data) {
        $('#HTMLModal_konten').html(data);
        $('#HTMLModal').modal('handleUpdate')
    });
}