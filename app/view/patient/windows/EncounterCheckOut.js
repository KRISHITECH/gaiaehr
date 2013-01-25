/**
 * Created with IntelliJ IDEA.
 * User: ernesto
 * Date: 1/17/13
 * Time: 5:14 PM
 * To change this template use File | Settings | File Templates.
 */
Ext.define('App.view.patient.windows.EncounterCheckOut', {
	extend:'App.ux.window.Window',
	title:i18n('checkout_and_signing'),
	closeAction:'hide',
	modal:true,
	layout:'border',
	width:1000,
	height:660,
	bodyPadding:5,

	pid:null,
	eid:null,

	initComponent:function(){
		var me = this;

		me.encounterCPTsICDsStore = Ext.create('App.store.patient.EncounterCPTsICDs');
		me.checkoutAlertArea = Ext.create('App.store.patient.CheckoutAlertArea');

		Ext.apply(me,{
			items:[
				me.servicesesGrid = Ext.widget('treepanel',{
					title:i18n('services_diagnostics'),
					rootVisible: false,
					region:'center',
					flex:2,
					store:me.encounterCPTsICDsStore,
					plugins:[
						{
							ptype: 'treeviewdragdrop'
						}
					],
					enableColumnMove:false,
					enableColumnHide:false,
					sortableColumns:false,
					useArrows:true,
					columns:[
						{
							xtype:'actioncolumn',
							width:20,
							items:[
								{
									icon:'resources/images/icons/delete.png',
									tooltip:i18n('remove'),
									scope:me,
									handler:me.onRemoveService,
									getClass: function(value, metadata, record){
										if(!record.data.leaf){
											return 'x-grid-center-icon';
										}else{
											return 'x-hide-display';
										}
									}
								}
							]
						},
						{
							xtype: 'treecolumn',
							text:i18n('code'),
							width: 120,
							dataIndex: 'code'
						},
						{
							header:i18n('description'),
							flex:1,
							dataIndex:'code_text_medium'
						}
					],
					dockedItems:[
						me.onQuickServiceToolbar = Ext.widget('toolbar',{
							dock:'left',
							items:[
								{
									xtype: 'buttongroup',
									title: i18n('new_patient'),
									flex: 1,
									columns: 1,
									defaults: {
										scale: 'small',
										padding:4,
										width:110,
										scope:me,
										handler:me.onQuickService
									},
									items: [
										{
											text: 'Brief',
											action:'99201'
										},
										{
											text: 'Limited',
											action:'99202'
										},
										{
											text: 'Detailed',
											action:'99203'
										},
										{
											text: 'Extended',
											action:'99204'
										},
										{
											text: 'Comprehensive',
											action:'99205'
										}
									]
								},{
									xtype: 'buttongroup',
									title: i18n('established_patient'),
									flex: 1,
									columns: 1,
									defaults: {
										scale: 'small',
										padding:4,
										width:110,
										scope:me,
										handler:me.onQuickService
									},
									items: [
										{
											text: 'Brief',
											action:'99211'
										},
										{
											text: 'Limited',
											action:'99212'
										},
										{
											text: 'Detailed',
											action:'99213'
										},
										{
											text: 'Extended',
											action:'99214'
										},
										{
											text: 'Comprehensive',
											action:'99215'
										}
									]
								}
							],
							listeners:{
								scope:me,
								beforerender:me.onQuickServiceBeforeRender
							}
						})
					]
				}),
				me.documentsimplegrid = Ext.create('App.view.patient.EncounterDocumentsGrid', {
					title:i18n('documents'),
					region:'east',
					flex:1
				}),
				{
					xtype:'form',
					title:i18n('additional_info'),
					region:'south',
					split:true,
					height:245,
					layout:'column',
					defaults:{
						xtype:'fieldset',
						padding:8
					},
					items:[
						{
							xtype:'fieldcontainer',
							columnWidth:.5,
							defaults:{
								xtype:'fieldset',
								padding:8
							},
							items:[
								{
									xtype:'fieldset',
									margin:'5 1 5 5',
									padding:8,
									columnWidth:.5,
									height:115,
									title:i18n('messages_notes_and_reminders'),
									items:[
										{
											xtype:'textfield',
											name:'message',
											fieldLabel:i18n('message'),
											anchor:'100%'
										},
										{
											xtype:'textfield',
											name:'reminder',
											fieldLabel:i18n('reminder'),
											anchor:'100%'
										},
										{
											xtype:'textfield',
											grow:true,
											name:'note',
											fieldLabel:i18n('note'),
											anchor:'100%'
										}
									]
								},
								{
									title:'Follow Up',
									margin:'5 1 5 5',
									defaults:{
										anchor:'100%'
									},
									items:[
										{
											xtype:'mitos.followupcombo',
											fieldLabel:i18n('time_interval'),
											name:'followup_time'
										},
										{
											fieldLabel:i18n('facility'),
											xtype:'mitos.activefacilitiescombo',
											name:'followup_facility'
										}
									]
								}
							]
						},
						{
							xtype:'fieldset',
							margin:5,
							padding:8,
							columnWidth:.5,
							layout:'fit',
							height:208,
							title:i18n('warnings_alerts'),
							items:[
								{
									xtype:'grid',
									hideHeaders:true,
									store:me.checkoutAlertArea,
									border:false,
									rowLines:false,
									header:false,
									viewConfig:{
										stripeRows:false,
										disableSelection:true
									},
									columns:[
										{
											dataIndex:'alertType',
											width:30,
											renderer:me.alertIconRenderer
										},
										{
											dataIndex:'alert',
											flex:1
										}
									]
								}
							]
						}
					]
				}
			],
			buttons:[
				{
					text:i18n('co_sign'),
					action:'encounter',
					scope:me,
					handler:me.coSignEncounter
				},
				{
					text:i18n('sign'),
					action:'encounter',
					scope:me,
					handler:me.signEncounter
				},
				{
					text:i18n('cancel'),
					scope:me,
					handler:me.cancelCheckout

				}
			],
			listeners:{
				scope:me,
				show:me.onWindowShow
			}
		});

		me.callParent();


	},

	onQuickService:function(btn){
		var root = this.encounterCPTsICDsStore.getRootNode(), rec, children;

		delete btn.data.id;
		btn.data.pid = this.pid;
		btn.data.eid = this.eid;
		btn.data.iconCls = 'icoDotGrey';

		rec = root.appendChild(btn.data);
		this.encounterCPTsICDsStore.sync({
			callback:function(batch){
				rec.set({id:batch.proxy.reader.rawData.id});
				rec.commit();

				children = batch.proxy.reader.rawData.dx_children;
				for(var i=0; i < children.length; i++){
					var child = children[i];
					child.code_text_medium = child.short_desc;
					child.leaf = true;
					child.iconCls = 'icoDotYellow';
					rec.appendChild(child);
				}
				rec.expand();
			}
		});

	},

	onQuickServiceBeforeRender:function(toolbar){
		Services.getQuickAccessCheckOutServices(function(provider, response){
			var services = response.result;
			for(var i=0; i < services.length; i++){
				toolbar.query('button[action="'+services[i].code+'"]')[0].data = services[i];
			}
		})
	},

	onRemoveService:function(grid, rowIndex, colIndex, item, e, record){
		say(record);
		this.encounterCPTsICDsStore.getRootNode().removeChild(record);
	},

	coSignEncounter:function(){

	},

	signEncounter:function(){
		this.enc.closeEncounter();
		this.close();
	},

	cancelCheckout:function(){
		this.close();
		this.down('form').getForm().reset();
	},

	onWindowShow:function(){
		var me = this;

		me.pid = me.enc.pid;
		me.eid = me.enc.eid;

		me.encounterCPTsICDsStore.load({params:{eid:me.eid}});
		if(acl['access_encounter_checkout']) me.checkoutAlertArea.load({params:{eid:app.patient.eid}});
		me.documentsimplegrid.loadDocs(app.patient.eid);
	},

	alertIconRenderer:function(v){
		if(v == 1){
			return '<img src="resources/images/icons/icoLessImportant.png" />'
		}else if(v == 2){
			return '<img src="resources/images/icons/icoImportant.png" />'
		}
		return v;
	}

});