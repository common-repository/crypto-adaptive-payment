var Cap = jQuery.noConflict();
Cap(document).ready(function(){
	
    Cap('#payment_method').on('change',function(){
		
		var thisMethod = Cap(this).val();
		if( thisMethod == 'adaptive_payment'){
			
			var capTxt = '<div style="margin-top: 26px;border: 1px solid lightgray;padding: 15px;width: 50%;background: lightblue;"><label>Admin Percentage: </label><input type="text" name="admin_percentage" value=""> <br/><br/><strong>[Please mention only the percentage without % sign , System will automatically calculated the seller cut.]</strong></div>';
			
			var Note = '<p>[note: Chained payment will be used.]</p>';
	
		}else{
			
			var capTxt = '';
			
			var Note = '<p>[note: 100% of the payment will go to the site owner/ You.]</p>';
		}
		
		
		Cap('#capTXT').html(capTxt);
		Cap('#note').html(Note);
		
	});	
	
	

	
	
});
function isNumberKey(txt, evt) {

    var charCode = (evt.which) ? evt.which : evt.keyCode;
    
    
    if (charCode == 46) {
        //Check if the text already contains the . character
        if (txt.value.indexOf('.') === -1) {
			//console.log('number is not only');
            return false;
        } else {
			//console.log('No dot is not there');
            return true;
        }
    } else {
        if (charCode > 31
             && (charCode < 48 || charCode > 57))
            return false;
    }
    return true;
}

	
