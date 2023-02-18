from django.test import TestCase
from django.core.files.uploadedfile import SimpleUploadedFile
from .models import Image
from .admin import ImageAdmin
from django.core.exceptions import ValidationError
from django.test import TestCase
from django.core.files.uploadedfile import SimpleUploadedFile
from django.contrib.admin.sites import site
from .models import Image
from .admin import ImageAdmin


class ImageAdminTest(TestCase):
    def setUp(self):
        # Create a test image file
        self.image_file = SimpleUploadedFile(
            "test_image.jpg", b"binarydata", content_type="image/jpeg"
        )

        # Create a test object with the image file
        self.test_object = Image.objects.create(
            name="Test Object", image=self.image_file
        )

    def tearDown(self):
        # Delete the test object and image file
        self.test_object.delete()
        self.image_file.close()

    def setUp(self):
        self.image = Image(name="Test Image")
        self.image.save()

    def tearDown(self):
        self.image.delete()

    def test_save_model_rejects_duplicate_image(self):
        with self.assertRaises(ValidationError):
            new_image = Image(name="Test Image")
            admin = ImageAdmin(Image, site)
            admin.save_model(None, new_image, form=None, change=None)

    def test_save_model_allows_new_image(self):
        # Create a new image object and save it
        new_image = Image(name="New Image")
        new_image.image = SimpleUploadedFile(
            name="new_image.jpg",
            content=open("images/new_image.jpg", "rb").read(),
            content_type="image/jpeg",
        )
        new_image.save()

        # Check that the file was saved to the correct location
        self.assertEqual(new_image.image.name, "images/new_image.jpg")

        # Clean up
        new_image.delete()
