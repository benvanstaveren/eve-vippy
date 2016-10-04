function refreshCharacterLocation(characterID)
{
    $("#characterRefreshSelector").hide();

    if (characterID)
    {
        var icon = $("#bttnRefreshCharacterLocation>img").attr("src");
        setRefreshCharacterBttnIcon("/images/loading.gif");
        $.ajax({
            url: "/crest/character/location/"+characterID,
            data: {
                ajax: 1
            },
            success: function(data) {
                data = $.parseJSON(data);
                if (data.errors == null) {
                    setRefreshCharacterBttnIcon("/images/default/apply.png");
                    setTimeout(function() {
                        setRefreshCharacterBttnIcon(icon);
                    }, 15000);
                } else {
                    setRefreshCharacterBttnIcon("/images/default/delete.png");
                    $("#mapButtons").before("<div class='error'><div><b>Character Refresh failed: </b> "+data.errors.join("<br />")+"</div></div>");
                }
            }
        });
    }
    else
    {
        $("#mapButtons").before("<div class='warning'><div><b>No character selected: </b> Please select a scan-alt in your <a href='/profile/account'>profile</a></div></div>");
    }
}

function setRefreshCharacterBttnIcon(icon)
{
    $("#bttnRefreshCharacterLocation>img").attr("src", icon);
}

function openRefreshCharacterSelector()
{
    if ($("#characterRefreshSelector").is(":visible"))
    {
        $("#characterRefreshSelector").hide();
    }
    else
    {
        $("#characterRefreshSelector").show();
        $("#characterRefreshSelector").css("top", $("#bttnRefreshCharacterLocation").position().top + $("#bttnRefreshCharacterLocation").outerHeight());
        $("#characterRefreshSelector").css("left", $("#bttnRefreshCharacterLocation").position().left);
    }
}