import pytest

from app.schemas.invoice import Invoice


def test_empty_arrays_coerce_to_dict():
    data = {
        "number": "INV-1",
        "date": "2025-01-01",
        "recipient": "Test LLC",
        "items": [
            {"description": "item", "quantity": 1, "price": 2, "reducerSpecs": []}
        ],
        "commercialTerms": [],
        "contact": [],
        "_metadata": [],
    }

    invoice = Invoice.model_validate(data)

    assert invoice.commercialTerms.model_dump() == {
        "incoterm": "",
        "deliveryPlace": "",
        "deliveryTime": "",
        "paymentTerms": "",
        "warranty": "",
    }
    assert invoice.contact.model_dump() == {
        "person": "",
        "position": "",
        "email": "",
        "phone": "",
    }
    assert invoice.meta_data == {}
    assert invoice.items[0].reducerSpecs == {}


@pytest.mark.parametrize(
    "quantity,price,expected_total",
    [
        ("2", "3.5", 7.0),
        (" 2,5 ", "4", 10.0),
        ("", "", 0.0),
        (None, None, 0.0),
    ],
)
def test_string_numbers_are_coerced(quantity, price, expected_total):
    data = {
        "number": "INV-2",
        "date": "2025-01-02",
        "recipient": "Test LLC",
        "items": [{"description": "item", "quantity": quantity, "price": price}],
    }

    invoice = Invoice.model_validate(data)
    item = invoice.items[0]
    assert item.quantity * item.price == pytest.approx(expected_total)


def test_metadata_alias_roundtrip():
    data = {
        "number": "INV-3",
        "date": "2025-01-03",
        "recipient": "Test LLC",
        "_metadata": {"saved_at": "now"},
    }

    invoice = Invoice.model_validate(data)
    assert invoice.meta_data == {"saved_at": "now"}

    dumped = invoice.model_dump(by_alias=True)
    assert dumped["_metadata"]["saved_at"] == "now"

