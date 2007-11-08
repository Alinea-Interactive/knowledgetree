using NUnit.Framework;
using System;
using System.IO;

namespace MonoTests.KnowledgeTree
{
	[TestFixture]
	public class CheckoutDocumentTest
    	{
		private String 			_session;
		private KnowledgeTreeService 	_kt;
		private int 			_docId;
		private int 			_folderId;
		private String			_filename;
		private String			_content;
		private bool			_verbose;


		[SetUp]
		public void SetUp()
		{
			this._kt = new KnowledgeTreeService();
			kt_response response = this._kt.login("admin","admin","127.0.0.1");
			this._session = response.message;

			this._filename = Helper.isUnix()?"/tmp/kt_unit_test1.txt":"c:\\kt_unit_test1.txt";

			String filename = "kt unit test1";

			this._content = "hello world!";

			Helper.writeFile(this._filename, this._content);

			this._verbose = false;

			this._folderId = 1;

			kt_document_detail response1 = this._kt.add_base64_document(this._session, this._folderId, filename, this._filename, "Default", Helper.ConvertFileToBase64Encoding(this._filename));

			if (this._verbose && response1.status_code != 0)
			{
				System.Console.WriteLine("Could not create file: " + this._filename);
			}
			this._docId = response1.document_id;


		}

		[TearDown]
		public void TearDown()
		{

			Helper.deleteFile(this._filename);

			kt_response response = this._kt.delete_document(this._session, this._docId, "Delete - cleaning up");
			if (this._verbose && response.status_code != 0)
			{
				System.Console.WriteLine("Could not delete file: " + this._filename);
			}

			this._kt.logout(this._session);

		}

		[Test]
		public void CheckoutDocument()
		{
			String filename = "kt unit test1";

			if (this._verbose) System.Console.WriteLine("Checking out document : " + filename);

			kt_response response = this._kt.checkout_base64_document(this._session, this._docId, "unit test - going to checkout and then undo", false);
			Assert.AreEqual(0, response.status_code);


			response = this._kt.undo_document_checkout(this._session, this._docId, "unit test - doing undo");
			Assert.AreEqual(0, response.status_code);
	    	}

		[Test]
		public void CheckinDocument()
		{
			String filename = "kt unit test1";

			if (this._verbose) System.Console.WriteLine("Checking out document : " + filename);

			kt_response response = this._kt.checkout_base64_document(this._session, this._docId, "unit test - going to checkout and then checkin", false);
			Assert.AreEqual(0, response.status_code);


			kt_document_detail checkin = this._kt.checkin_base64_document(this._session, this._docId, filename, "unit test - doing checkin", Helper.ConvertFileToBase64Encoding(this._filename), false);
			Assert.AreEqual(0, checkin.status_code);

			//assert - check data checkout
	    	}

		[Test]
		public void Checkin2PhaseDocument()
		{
			String filename = "kt unit test1";

			if (this._verbose) System.Console.WriteLine("Checking out document : " + filename);

			kt_response response = this._kt.checkout_document(this._session, this._docId, "unit test - going to checkout and then checkin", false);
			Assert.AreEqual(0, response.status_code);


			FileUploader uploader = new FileUploader("http://ktdms.trunk/ktwebservice/upload.php");

			uploader.upload(this._session, this._filename);
			String tempname = uploader.getFilename();

			kt_document_detail checkin = this._kt.checkin_document(this._session, this._docId, filename, "unit test - doing checkin", tempname, false);
			Assert.AreEqual(0, checkin.status_code);
	    	}



	}
}