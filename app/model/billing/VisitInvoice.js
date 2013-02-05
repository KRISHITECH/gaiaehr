/**
 * Created by JetBrains PhpStorm.
 * User: Ernesto J. Rodriguez (Certun)
 * File:
 * Date: 2/18/12
 * Time: 11:09 PM
 */
Ext.define('App.model.billing.VisitInvoice', {
    extend: 'Ext.data.Model',
    fields: [
        {name: 'id', type:'int'},
        {name: 'code', type: 'string'},
        {name: 'code_text_medium', type: 'string'},
	    {name: 'charge', type: 'float', convert:function(v){
            return parseFloat(v).toFixed(2);
        }},
	    {name: 'payer_type', type: 'int', defaultValue:0}
    ],
    proxy : {
        type  : 'direct',
        api   : {
            read: Billing.getVisitInvoice
        }
    }
});