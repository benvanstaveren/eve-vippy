
function loadSignatureList(noCache)
{
	if (editingSigList)
		return false;

	if (!loadingSigList && $("#disabledPage").length == 0 && $("#signatureList").is(":visible"))
	{
        var params = { ajax: 1 };
		if (noCache)
			params.nocache = 1;

		loadingSigList = true;
		$.ajax({
			url: "/map/"+$("#mapName").val()+"/signatures/"+$("#mapSystem").val(),
            data: params,
			success: function(data) {
				if (data != "cached" && !editingSigList) {
                    // Oude sigs
                    var old = [];
                    $("tr[rel=signature]").each(function() {
                        old[$(this).attr("data-id")] = {
                            id: $(this).attr("data-id"),
                            type: $(this).attr("data-type")
                        };
                        $(this).remove();
                    });

                    // Nieuwe sigs
                    var signatures = $.parseJSON(data);
                    $("#signaturesCount").html(signatures.length);
                    for (var s=0; s<signatures.length; s++) {
                        $("#signatureTable").append(Mustache.to_html($("#signatureTPL").html(), {
                            id: signatures[s].id,
                            sigid: signatures[s].sigid,
                            type: signatures[s].type,
                            whtype: (signatures[s].wormhole!=undefined) ? signatures[s].wormhole.type : "",
                            info: signatures[s].info,
                            scanage: signatures[s].scanage
                        }));
                    }
                }

				loadingSigList = false;
			}
		});
	}
}

function editSignature(id)
{
    $("tr.sigedit").each(function() {
        editSignatureCancel($(this).attr("data-id"));
    });

    editingSigList = true;
    var row = $("tr[data-id="+id+"]");
    var data = {
        id: id,
        sigid: row.find("td.sigID").html(),
        type: row.attr("data-type"),
        whtype: row.attr("data-whtype"),
        info: row.find("td.sigInfo").html(),
        scanage: row.find("td.sigUpdate").html()
    };

    var html = Mustache.to_html($("#signatureEditTPL").html(), data);
    $("tr[data-id="+id+"]").replaceWith(html);

    $("#sigType"+id).val(data.type);
    $("input.signame").focus();
    $("input.signame").select();

    if (data.type == "wh") {
        $("input.signame").width($("td.sigInfo").width()-$("input.whtype").width()-50);
    } else {
        $("input.whtype").hide();
        $("input.signame").width($("td.sigInfo").width()-50);
    }
}

function editSignatureCancel(id)
{
    editingSigList = false;
    var html = Mustache.to_html($("#signatureTPL").html(), {
        id: id,
        sigid: $("#sigId"+id).val(),
        type: $("#sigType"+id).val(),
        whtype: $("#whType"+id).val(),
        info: $("#sigName"+id).val(),
        scanage: $("tr[data-id="+id+"]").find("sigUpdate").html()
    });
    $("tr[data-id="+id+"]").replaceWith(html);
}

function addSignature()
{
    var params = {
        id: 0,
        systemid: $("#mapSystem").val(),
        sigid: $("#sigId").val(),
        type: $("#sigType").val(),
        whtype: $("#whType").val(),
        info: $("#sigName").val(),
        ajax: 1
    };

    $.ajax({
        url: "/map/"+$("#mapName").val()+"/signatures/store",
        data: params,
        complete: function() {
            loadSignatureList(true);
            $("#sigId").val("");
            $("#sigType").val("");
            $("#whType").val("");
            $("#sigName").val("");
            $("#sigId").focus();
        }
    });
}

function storeSignature()
{
    var id = $("tr.sigedit").attr("data-id");
    var params = {
        id: id,
        systemid: $("#mapSystem").val(),
        sigid: $("#sigId"+id).val(),
        type: $("#sigType"+id).val(),
        whtype: $("#whType"+id).val(),
        info: $("#sigName"+id).val(),
        ajax: 1
    };

    $.ajax({
        url: "/map/"+$("#mapName").val()+"/signatures/store",
        data: params,
        complete: function() {
            editingSigList = false;
            loadSignatureList(true);
        }
    });
}

function markFullyScanned(systemID)
{
	// loadSignatureList();
}


function showSigInfo(sigID)
{
    var top = $("#signatureList"+sigID).position().top-30;
    var left = $("#signatureList"+sigID).position().left+$("#signatureList"+sigID).width();

    if ($(window).width() < 930)
    {
        left -= ($("#sigInfo"+sigID).width()+60);
        $("#sigInfo"+sigID).find("div.content").removeClass("contentleft");
        $("#sigInfo"+sigID).find("div.content").addClass("contentright");
    }
    else
    {
        $("#sigInfo"+sigID).find("div.content").removeClass("contentright");
        $("#sigInfo"+sigID).find("div.content").addClass("contentleft");
    }

	$("#sigInfo"+sigID).css("left", left);
	$("#sigInfo"+sigID).css("top", top);
    $("#sigInfo"+sigID).fadeIn();
	loadingSigList = true;
}


function selectSignatureType(sigID)
{
    var sigType =  $("#sigType").val();

    if (sigType == "wh")
    {
        var html = "";
        var data = {
            sigtype: sigType,
            sigID: sigID
        };
        if ($("td[rel=addsig_wormhole]").attr("data-whtype-input") == "select")
            html = Mustache.to_html($("#whTypeSelectTPL").html(), data);
        else
            html = Mustache.to_html($("#whTypeInputTPL").html(), data);

        $("#whTypeInputContainer").html(html);
        $("td[rel=addsig_wormhole]").show();
        $("#whType").focus();
    }
    else
    {
        $("td[rel=addsig_wormhole]").hide();
        $("#sigName").focus();
    }
}