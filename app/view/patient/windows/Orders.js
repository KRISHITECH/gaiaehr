/**
 * GaiaEHR (Electronic Health Records)
 * Copyright (C) 2013 Certun, LLC.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

Ext.define('App.view.patient.windows.Orders', {
	extend: 'App.ux.window.Window',
	requires:[
		'App.view.patient.LabOrders',
		'App.view.patient.RadOrders',
		'App.view.patient.RxOrders',
		'App.view.patient.DoctorsNotes'
	],
	title: _('order_window'),
	closeAction: 'hide',
	bodyStyle: 'background-color:#fff',
	modal: true,
	buttons: [
		{
			text: _('close'),
			handler: function(btn){
				btn.up('window').close();
			}
		}
	],
	initComponent: function(){
		var me = this;

		me.items = [
			me.tabPanel = Ext.create('Ext.tab.Panel', {
				margin: 5,
				height: Ext.getBody().getHeight() < 700 ? (Ext.getBody().getHeight() - 100) : 600,
				width: Ext.getBody().getWidth() < 1550 ? (Ext.getBody().getWidth() - 50) : 1500,
				plain: true,
				items: [
					/**
					 * LAB ORDERS PANEL
					 */
					{
						xtype: 'patientlaborderspanel'
					},
					/**
					 * X-RAY PANEL
					 */
					{
						xtype: 'patientradorderspanel'
					},
					/**
					 * PRESCRIPTION PANEL
					 */
					{
						xtype:'patientrxorderspanel'
					},
					/**
					 * DOCTORS NOTE
					 */
					{
						xtype: 'patientdoctorsnotepanel'
					}
				]

			})
		];

		me.buttons = [
			{
				text: _('close'),
				scope: me,
				handler: function(){
					me.close();
				}
			}
		];
		/**
		 * windows listeners
		 * @type {{scope: *, show: Function, hide: Function}}
		 */
		me.listeners = {
			scope: me,
			show: me.onWinShow,
			hide: me.onWinHide
		};
		me.callParent(arguments);
	},

	/**
	 * OK!
	 * @param action
	 */
	cardSwitch: function(action){
		var me = this,
			tabPanel = me.tabPanel,
			activePanel = tabPanel.getActiveTab(),
			toPanel =  tabPanel.query('#'+ action)[0];

		if(activePanel == toPanel){
			activePanel.fireEvent('activate', activePanel);
		}else{
			tabPanel.setActiveTab(toPanel);
			me.setWindowTitle(toPanel.title);
		}
	},

	setWindowTitle:function(title){
		this.setTitle(app.patient.name + ' (' + title + ') ' + (app.patient.readOnly ? '-  <span style="color:red">[Read Mode]</span>' :''));
	},


	/**
	 * OK!
	 * On window shows
	 */
	onWinShow: function(){
		var me = this,
			p = me.down('tabpanel'),
			w = Ext.getBody().getWidth() < 1550 ? (Ext.getBody().getWidth() - 50) : 1500,
			h = Ext.getBody().getHeight() < 700 ? (Ext.getBody().getHeight() - 100) : 600;

		p.setSize(w, h);

		me.alignTo(Ext.getBody(), 'c-c');
		/**
		 * Fire Event
		 */
		me.fireEvent('orderswindowshow', me);
		/**
		 * read only stuff
		 */
		me.setTitle(app.patient.name + ' - ' + _('orders') + (app.patient.readOnly ? ' - <span style="color:red">[' + _('read_mode') + ']</span>' : ''));
		me.setReadOnly(app.patient.readOnly);
	},

	/**
	 * OK!
	 * Loads patientDocumentsStore with new documents
	 */
	onWinHide: function(){
		var me = this;
		/**
		 * Fire Event
		 */
		me.fireEvent('orderswindowhide', me);
		if(app.getActivePanel().$className == 'App.view.patient.Summary'){
			app.getActivePanel().loadStores();
		}

	}

});