$(document).ready(function()
{$("#buyProducts").on('click', function(event){
    event.stopPropagation();
    event.stopImmediatePropagation();
    prodCounts = $(".productCount");
    console.log(prodCounts.length);
    var em = $.trim($('#customerEmail').val());
        if(em.length < 5){
            alert('Please enter your email.');
            return;
        }

    $("#results").empty();
    for(let i =0; i < prodCounts.length; i++){
        prodID = $(prodCounts[i]).data("productid");
        count = $(prodCounts[i]).val();
        if (count > 0)
        {
            $.get( "/products.php?action=buy&quantity=" +count.toString() + "&itemID=" + prodID.toString() + "&customerEmail=" + em, function(data) {
                $("#results").append("<br><color>" +  $(prodCounts[i]).val().toString() + " " + $(prodCounts[i]).data("productname") + " ordered.")
                console.log(data);
            });
        }
    }

})})
