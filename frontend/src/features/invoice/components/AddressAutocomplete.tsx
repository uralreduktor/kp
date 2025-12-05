import { useState, useEffect, useRef, useCallback } from 'react';
import { useFormContext } from 'react-hook-form';
import { invoiceService } from '../service';
import { MapPin, Loader2 } from 'lucide-react';

interface AddressSuggestion {
  value: string;
  unrestricted_value?: string;
  data?: {
    postal_code?: string;
    country?: string;
    region?: string;
    city?: string;
    street?: string;
    house?: string;
    fias_id?: string;
    geo_lat?: string;
    geo_lon?: string;
  };
}

interface AddressAutocompleteProps {
  /** Имя поля для адреса */
  fieldName: string;
  /** Placeholder для input */
  placeholder?: string;
  /** Label для поля */
  label?: string;
}

export function AddressAutocomplete({
  fieldName,
  placeholder = 'г. Екатеринбург, ул...',
  label,
}: AddressAutocompleteProps) {
  const { register, watch, setValue, formState: { errors } } = useFormContext();
  const [addressValue, setAddressValue] = useState(watch(fieldName) || '');
  const [suggestions, setSuggestions] = useState<AddressSuggestion[]>([]);
  const [isLoading, setIsLoading] = useState(false);
  const [showDropdown, setShowDropdown] = useState(false);
  const [selectedIndex, setSelectedIndex] = useState(-1);
  const debounceRef = useRef<NodeJS.Timeout | null>(null);
  const dropdownRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLInputElement | null>(null);

  // Отслеживаем изменения адреса
  useEffect(() => {
    const subscription = watch((value, { name }) => {
      if (name === fieldName) {
        setAddressValue(value[fieldName] as string || '');
      }
    });
    return () => subscription.unsubscribe();
  }, [watch, fieldName]);

  // Поиск адресов
  const searchAddresses = useCallback(async (query: string) => {
    if (!query || query.length < 3) {
      setSuggestions([]);
      setShowDropdown(false);
      return;
    }

    setIsLoading(true);
    try {
      const results = await invoiceService.searchAddresses(query, 10);
      setSuggestions(results);
      setShowDropdown(results.length > 0);
    } catch (error) {
      console.error('Error searching addresses:', error);
      setSuggestions([]);
      setShowDropdown(false);
    } finally {
      setIsLoading(false);
    }
  }, []);

  // Debounced поиск
  useEffect(() => {
    if (debounceRef.current) {
      clearTimeout(debounceRef.current);
    }

    const query = addressValue?.trim() || '';
    if (query.length >= 3) {
      debounceRef.current = setTimeout(() => {
        searchAddresses(query);
      }, 300);
    } else {
      setSuggestions([]);
      setShowDropdown(false);
    }

    return () => {
      if (debounceRef.current) {
        clearTimeout(debounceRef.current);
      }
    };
  }, [addressValue, searchAddresses]);

  // Обработка выбора адреса
  const handleSelectAddress = useCallback((address: AddressSuggestion) => {
    setValue(fieldName, address.value);
    setSuggestions([]);
    setShowDropdown(false);
    setAddressValue(address.value);
  }, [setValue, fieldName]);

  // Обработка клавиатуры
  const handleKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
    if (!showDropdown || suggestions.length === 0) return;

    if (e.key === 'ArrowDown') {
      e.preventDefault();
      setSelectedIndex((prev) => (prev < suggestions.length - 1 ? prev + 1 : prev));
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      setSelectedIndex((prev) => (prev > 0 ? prev - 1 : -1));
    } else if (e.key === 'Enter' && selectedIndex >= 0) {
      e.preventDefault();
      handleSelectAddress(suggestions[selectedIndex]);
    } else if (e.key === 'Escape') {
      setShowDropdown(false);
      setSelectedIndex(-1);
    }
  };

  // Закрытие dropdown при клике вне
  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (
        dropdownRef.current &&
        !dropdownRef.current.contains(event.target as Node) &&
        inputRef.current &&
        !inputRef.current.contains(event.target as Node)
      ) {
        setShowDropdown(false);
        setSelectedIndex(-1);
      }
    };

    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  return (
    <div className="space-y-2 relative">
      {label && <label className="block text-sm font-medium text-gray-700">{label}</label>}
      <div className="relative">
        <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
          {isLoading ? (
            <Loader2 size={16} className="animate-spin" />
          ) : (
            <MapPin size={16} />
          )}
        </div>
        <input
          {...register(fieldName)}
          ref={(e) => {
            inputRef.current = e;
            register(fieldName).ref(e);
          }}
          value={addressValue}
          onChange={(e) => {
            setAddressValue(e.target.value);
            register(fieldName).onChange(e);
          }}
          onFocus={() => {
            if (suggestions.length > 0) {
              setShowDropdown(true);
            }
          }}
          onKeyDown={handleKeyDown}
          className="pl-10 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2 border"
          placeholder={placeholder}
          type="text"
        />
        {showDropdown && suggestions.length > 0 && (
          <div
            ref={dropdownRef}
            className="absolute z-50 mt-1 w-full bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-auto"
          >
            {suggestions.map((address, index) => (
              <button
                key={index}
                type="button"
                onClick={() => handleSelectAddress(address)}
                className={`w-full text-left px-4 py-3 hover:bg-blue-50 focus:bg-blue-50 focus:outline-none transition-colors ${
                  index === selectedIndex ? 'bg-blue-50' : ''
                }`}
              >
                <div className="font-medium text-gray-900">{address.value}</div>
                {address.data?.postal_code && (
                  <div className="text-xs text-gray-500 mt-1">
                    {address.data.postal_code}
                    {address.data?.city && `, ${address.data.city}`}
                  </div>
                )}
              </button>
            ))}
          </div>
        )}
      </div>
      {errors[fieldName] && (
        <p className="text-xs text-red-500">{errors[fieldName]?.message as string}</p>
      )}
    </div>
  );
}

