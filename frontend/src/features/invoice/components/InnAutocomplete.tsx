import { useState, useEffect, useRef, useCallback } from 'react';
import { useFormContext } from 'react-hook-form';
import { invoiceService } from '../service';
import type { InvoiceFormData } from '../schema';
import { Building2, Loader2 } from 'lucide-react';

// Helper type to get only keys with string values
type StringKey<T> = {
  [K in keyof T]: T[K] extends string | undefined | null ? K : never
}[keyof T];

interface CompanySuggestion {
  value: string;
  inn?: string;
  name?: string;
  name_short?: string;
  address?: string;
  management?: string;
  data?: {
    inn?: string;
    name?: {
      full_with_opf?: string;
      short_with_opf?: string;
    };
    address?: {
      value?: string;
    };
    management?: {
      name?: string;
      post?: string;
    };
  };
}

interface InnAutocompleteProps {
  innFieldName?: StringKey<InvoiceFormData>;
  nameFieldName?: StringKey<InvoiceFormData>;
  addressFieldName?: StringKey<InvoiceFormData>;
}

export function InnAutocomplete({
  innFieldName = 'recipientINN',
  nameFieldName = 'recipient',
  addressFieldName = 'recipientAddress',
}: InnAutocompleteProps) {
  const { register, watch, setValue, formState: { errors } } = useFormContext<InvoiceFormData>();
  
  // Watch for external changes to the field (e.g. imports, resets)
  const innValueFromForm = watch(innFieldName);
  
  // Local state for the input value to allow debouncing and controlled input
  const [innValue, setInnValue] = useState<string>('');
  
  // Sync local state when form value changes externally (and on initial load)
  useEffect(() => {
    const val = innValueFromForm !== undefined && innValueFromForm !== null ? String(innValueFromForm) : '';
    setInnValue(val);
  }, [innValueFromForm]);

  const [suggestions, setSuggestions] = useState<CompanySuggestion[]>([]);
  const [isLoading, setIsLoading] = useState(false);
  const [showDropdown, setShowDropdown] = useState(false);
  const [selectedIndex, setSelectedIndex] = useState(-1);
  const debounceRef = useRef<NodeJS.Timeout | null>(null);
  const dropdownRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLInputElement | null>(null);

  // Register the field once
  const { ref: formRef, onChange: formOnChange, ...formRest } = register(innFieldName);

  // Поиск компании по ИНН
  const searchCompany = useCallback(async (inn: string) => {
    if (!inn || inn.length < 10) {
      setSuggestions([]);
      return;
    }

    const cleanInn = inn.replace(/\D/g, '');
    if (cleanInn.length !== 10 && cleanInn.length !== 12) {
      setSuggestions([]);
      return;
    }

    setIsLoading(true);
    try {
      console.log('Searching company by INN:', cleanInn);
      const company = await invoiceService.getCompanyByINN(cleanInn);
      console.log('Company result:', company);
      if (company) {
        setSuggestions([company]);
        setShowDropdown(true);
      } else {
        setSuggestions([]);
        setShowDropdown(false);
      }
    } catch (error) {
      console.error('Error searching company:', error);
      setSuggestions([]);
      setShowDropdown(false);
    } finally {
      setIsLoading(false);
    }
  }, []);

  // Handle input change
  const handleInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const value = e.target.value;
    setInnValue(value);
    
    // Pass to RHF
    formOnChange(e);

    // Debounce search
    if (debounceRef.current) {
      clearTimeout(debounceRef.current);
    }

    const trimmed = value.trim();
    if (trimmed.length >= 10) {
      debounceRef.current = setTimeout(() => {
        searchCompany(trimmed);
      }, 500);
    } else {
      setSuggestions([]);
      setShowDropdown(false);
    }
  };

  // Обработка выбора компании
  const handleSelectCompany = useCallback((company: CompanySuggestion) => {
    // ... logic same as before ...
    const inn = company.inn || company.data?.inn || '';
    const name = company.name_short || company.name || company.data?.name?.short_with_opf || company.data?.name?.full_with_opf || company.value || '';
    const address = company.address || company.data?.address?.value || '';

    // Update form
    if (inn) setValue(innFieldName, inn, { shouldValidate: true, shouldDirty: true });
    if (nameFieldName && name) setValue(nameFieldName, name, { shouldValidate: true, shouldDirty: true });
    if (addressFieldName && address) setValue(addressFieldName, address, { shouldValidate: true, shouldDirty: true });

    setSuggestions([]);
    setShowDropdown(false);
    // innValue will be updated via useEffect when form value changes
  }, [setValue, innFieldName, nameFieldName, addressFieldName]);

  // Keyboard & ClickOutside logic...
  const handleKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
      if (!showDropdown || suggestions.length === 0) return;
      if (e.key === 'ArrowDown') {
        e.preventDefault();
        setSelectedIndex(prev => (prev < suggestions.length - 1 ? prev + 1 : prev));
      } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        setSelectedIndex(prev => (prev > 0 ? prev - 1 : -1));
      } else if (e.key === 'Enter' && selectedIndex >= 0) {
        e.preventDefault();
        handleSelectCompany(suggestions[selectedIndex]);
      } else if (e.key === 'Escape') {
        setShowDropdown(false);
        setSelectedIndex(-1);
      }
  };

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
      <label className="block text-sm font-medium text-gray-700">ИНН (для автозаполнения)</label>
      <div className="relative">
        <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
          {isLoading ? <Loader2 size={16} className="animate-spin" /> : <Building2 size={16} />}
        </div>
        <input
          {...formRest} // Pass name, onBlur, etc.
          ref={(e) => {
            formRef(e);
            inputRef.current = e;
          }}
          onChange={handleInputChange} // Override onChange
          value={innValue} // Controlled
          onFocus={() => {
            if (suggestions.length > 0) setShowDropdown(true);
          }}
          onKeyDown={handleKeyDown}
          className="pl-10 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2 border"
          placeholder="66..."
          type="text"
          autoComplete="off"
        />
        {showDropdown && suggestions.length > 0 && (
          <div
            ref={dropdownRef}
            className="absolute z-50 mt-1 w-full bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-auto"
          >
            {suggestions.map((company, index) => (
              <button
                key={index}
                type="button"
                onClick={() => handleSelectCompany(company)}
                className={`w-full text-left px-4 py-3 hover:bg-blue-50 focus:bg-blue-50 focus:outline-none transition-colors ${
                  index === selectedIndex ? 'bg-blue-50' : ''
                }`}
              >
                <div className="font-medium text-gray-900">
                  {company.name || company.name_short || company.data?.name?.full_with_opf || company.data?.name?.short_with_opf || company.value}
                </div>
                {(company.inn || company.data?.inn) && (
                  <div className="text-sm text-gray-500 mt-1">ИНН: {company.inn || company.data?.inn}</div>
                )}
                {(company.address || company.data?.address?.value) && (
                   <div className="text-xs text-gray-400 mt-1">{company.address || company.data?.address?.value}</div>
                )}
              </button>
            ))}
          </div>
        )}
      </div>
      {errors[innFieldName] && (
        <p className="text-xs text-red-500">{errors[innFieldName]?.message as string}</p>
      )}
       {typeof innValue === 'string' && innValue.length >= 10 && suggestions.length === 0 && !isLoading && (
        <p className="text-xs text-gray-500">Компания не найдена</p>
      )}
    </div>
  );
}
