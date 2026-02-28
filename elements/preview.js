class Preview {
        constructor() {}
        
        bindRecTemplateChange() {
                var instance = this;
                $("#rectemplate").off("click").on("click", function (e) {
                        e.stopImmediatePropagation();
                        e.preventDefault();
                        instance.rerenderRecPreview();
                });
        }
        
        rerenderRecPreview() {
                var template = $("#rectemplate").val();
                var data = {
                        template: template,
                        size: 40,
                        n: getMillis()
                };
                doAjax("POST","php/contenthandler.php?module=preview&command=recpreview&v=2.9.12",data,this.handleRecPreview,null);
        }
        
        handleRecPreview(answer) {
                var txt = "?";
                if (answer.status === "OK") {
                        txt = answer.msg;
                        $("#recpreviewarea").html(txt);
                }
        }
}