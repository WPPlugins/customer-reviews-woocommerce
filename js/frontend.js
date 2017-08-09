jQuery(document).ready(function($) {
  //enable attachment of images to comments
  jQuery('form#commentform').attr( "enctype", "multipart/form-data" ).attr( "encoding", "multipart/form-data" );
  //prevent review submission if captcha is not solved
  jQuery("#commentform").submit(function(event) {
    var recaptcha = jQuery("#g-recaptcha-response").val();
    if (recaptcha === "") {
      event.preventDefault();
      alert("Please confirm that you are not a robot");
    }
  });
});
