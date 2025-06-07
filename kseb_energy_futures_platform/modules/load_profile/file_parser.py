"""
Parses the load curve template Excel file for the Load Profile Generation module.

This module contains functions to read and process data from various sheets
within the `load_curve_template.xlsx`, including past hourly demand,
total annual demand targets, and optional monthly peak demand and load factor targets.
It structures this data into pandas DataFrames for use in load profile generation.
"""
import pandas as pd

# TODO: Add logging configuration if a platform-wide logger is established.
# For now, will use print for warnings/errors during parsing.

def _normalize_column_names(df: pd.DataFrame) -> pd.DataFrame:
    """
    Normalizes column names in a DataFrame: converts to lowercase and replaces spaces with underscores.
    """
    if df is not None and not df.empty:
        df.columns = [str(col).strip().lower().replace(' ', '_') for col in df.columns]
    return df

def process_load_curve_template(file_path: str) -> dict | None:
    """
    Loads and processes data from the load curve template Excel file.

    This function reads multiple sheets:
    - 'Past_Hourly_Demand': Expects 'date', 'time', 'demand'. Processes into hourly demand.
    - 'Total Demand': Expects 'financial_year' (or similar) and 'Total demand' (or similar).
    - 'max_demand' (optional): Expects 'financial_year' and monthly columns (Apr-Mar) for peak targets.
    - 'load_factors' (optional): Expects 'financial_year' and 'load_factor' for annual load factor targets.

    Args:
        file_path (str): The path to the input Excel file (e.g., 'load_curve_template.xlsx').

    Returns:
        dict | None: A dictionary containing the processed data:
            'past_hourly_demand' (pd.DataFrame): Hourly demand data with datetime index.
            'total_annual_demand' (pd.DataFrame): Annual total demand targets.
            'monthly_peak_targets' (pd.DataFrame | None): Monthly peak demand targets, or None.
            'annual_load_factor_targets' (pd.DataFrame | None): Annual load factor targets, or None.
            Returns None if critical errors occur (e.g., file not found, essential sheets/columns missing).
    """
    print(f"Loading load curve template data from: {file_path}")

    past_hourly_demand_df = pd.DataFrame()
    total_annual_demand_df = pd.DataFrame()
    monthly_peak_targets_df = None
    annual_load_factor_targets_df = None

    try:
        # 1. Read 'Past_Hourly_Demand' sheet
        try:
            sheet_df = pd.read_excel(file_path, sheet_name='Past_Hourly_Demand')
            sheet_df = _normalize_column_names(sheet_df)

            if not all(col in sheet_df.columns for col in ['date', 'time', 'demand']):
                print("Error: 'Past_Hourly_Demand' sheet must contain 'date', 'time', and 'demand' columns.")
                return None # Critical error if these columns are missing

            # Combine 'date' and 'time' into a datetime column
            # Ensure 'date' is treated as string to avoid Excel date serial number issues if not already datetime
            sheet_df['datetime_col'] = pd.to_datetime(
                sheet_df['date'].astype(str) + ' ' + sheet_df['time'].astype(str),
                errors='coerce' # Coerce errors will result in NaT
            )

            # Drop rows where datetime conversion failed
            sheet_df.dropna(subset=['datetime_col'], inplace=True)
            if sheet_df.empty:
                 print("Error: No valid datetime entries found in 'Past_Hourly_Demand' after combining date and time.")
                 return None

            sheet_df.set_index('datetime_col', inplace=True)

            # Select and resample the demand column
            demand_series = sheet_df['demand']
            if not pd.api.types.is_numeric_dtype(demand_series):
                demand_series = pd.to_numeric(demand_series, errors='coerce')
                demand_series.dropna(inplace=True) # Drop rows where demand is not numeric

            # Resample to hourly frequency. Use sum() if data could be sub-hourly and needs aggregation.
            # Use mean() if data is already hourly but might have multiple entries per hour (e.g. from different sources).
            # Using sum() is generally safer for demand data that might represent consumption over intervals.
            past_hourly_demand_df = demand_series.resample('h').sum() # Changed 'H' to 'h'
            # If you expect single point values for each hour and want to avoid issues with multiple entries,
            # you might group by hour first and then take the mean/first, then resample.
            # For simplicity, 'resample.sum()' is used here.

            if past_hourly_demand_df.empty and not demand_series.empty:
                print("Warning: Resampling 'Past_Hourly_Demand' resulted in an empty series, though initial data was present. Check data time range and frequency.")
            elif past_hourly_demand_df.empty:
                 print("Warning: 'Past_Hourly_Demand' sheet processed, but no valid numeric demand data found or all data was filtered out.")

            print(f"'Past_Hourly_Demand' sheet processed. {len(past_hourly_demand_df)} hourly records found.")

        except Exception as e:
            print(f"Error processing 'Past_Hourly_Demand' sheet: {e}")
            return None # This sheet is critical

        # 2. Read 'Total Demand' sheet
        try:
            sheet_df = pd.read_excel(file_path, sheet_name='Total Demand')
            sheet_df = _normalize_column_names(sheet_df)

            # Flexible column name detection for financial year and total demand
            year_col_options = ['financial_year', 'year', 'fy']
            demand_col_options = ['total_demand', 'annual_demand', 'total_demand_gwh', 'total_demand_mu']

            year_col = next((col for col in year_col_options if col in sheet_df.columns), None)
            demand_col = next((col for col in demand_col_options if col in sheet_df.columns), None)

            if not year_col or not demand_col:
                print("Error: 'Total Demand' sheet must contain a year column (e.g., 'financial_year') and a demand column (e.g., 'Total demand').")
                return None # This sheet is critical

            total_annual_demand_df = sheet_df[[year_col, demand_col]].copy()
            total_annual_demand_df.rename(columns={year_col: 'financial_year', demand_col: 'annual_total_demand'}, inplace=True)

            # Ensure numeric type for demand
            if not pd.api.types.is_numeric_dtype(total_annual_demand_df['annual_total_demand']):
                total_annual_demand_df['annual_total_demand'] = pd.to_numeric(total_annual_demand_df['annual_total_demand'], errors='coerce')
                total_annual_demand_df.dropna(subset=['annual_total_demand'], inplace=True)

            print(f"'Total Demand' sheet processed. {len(total_annual_demand_df)} records found.")

        except Exception as e:
            print(f"Error processing 'Total Demand' sheet: {e}")
            return None # This sheet is critical

        # 3. Read 'max_demand' sheet (Optional)
        try:
            sheet_df = pd.read_excel(file_path, sheet_name='max_demand')
            sheet_df = _normalize_column_names(sheet_df)
            year_col_peak = next((col for col in ['financial_year', 'year', 'fy'] if col in sheet_df.columns), None)

            if year_col_peak and not sheet_df.empty:
                # Check for month columns (Apr, May, ..., Mar) - case insensitive due to _normalize_column_names
                month_cols = [m.lower() for m in ['Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec', 'Jan', 'Feb', 'Mar']]
                expected_month_cols = [m for m in month_cols if m in sheet_df.columns]

                if expected_month_cols:
                    monthly_peak_targets_df = sheet_df[[year_col_peak] + expected_month_cols].copy()
                    monthly_peak_targets_df.rename(columns={year_col_peak: 'financial_year'}, inplace=True)
                    print(f"'max_demand' sheet processed. {len(monthly_peak_targets_df)} records found.")
                else:
                    print("Warning: 'max_demand' sheet found, but standard month columns (Apr, May, etc.) are missing.")
            elif not sheet_df.empty:
                 print("Warning: 'max_demand' sheet found, but 'financial_year' column is missing.")
            else:
                print("Info: 'max_demand' sheet is empty or not found. Proceeding without monthly peak targets.")
        except Exception as e:
            print(f"Info: Could not process 'max_demand' sheet (optional): {e}")
            monthly_peak_targets_df = None # Ensure it's None if any error

        # 4. Read 'load_factors' sheet (Optional)
        try:
            sheet_df = pd.read_excel(file_path, sheet_name='load_factors')
            sheet_df = _normalize_column_names(sheet_df)
            year_col_lf = next((col for col in ['financial_year', 'year', 'fy'] if col in sheet_df.columns), None)
            lf_col = next((col for col in ['load_factor', 'annual_load_factor', 'lf'] if col in sheet_df.columns), None)

            if year_col_lf and lf_col and not sheet_df.empty:
                annual_load_factor_targets_df = sheet_df[[year_col_lf, lf_col]].copy()
                annual_load_factor_targets_df.rename(columns={year_col_lf: 'financial_year', lf_col: 'load_factor'}, inplace=True)

                # Ensure numeric type for load_factor, handling potential percentage strings
                def to_decimal_load_factor(val):
                    if isinstance(val, str):
                        if '%' in val:
                            return pd.to_numeric(val.rstrip('%'), errors='coerce') / 100
                    return pd.to_numeric(val, errors='coerce')

                annual_load_factor_targets_df['load_factor'] = annual_load_factor_targets_df['load_factor'].apply(to_decimal_load_factor)
                annual_load_factor_targets_df.dropna(subset=['load_factor'], inplace=True)

                print(f"'load_factors' sheet processed. {len(annual_load_factor_targets_df)} records found.")
            elif not sheet_df.empty:
                 print("Warning: 'load_factors' sheet found, but required columns ('financial_year', 'load_factor') are missing.")
            else:
                print("Info: 'load_factors' sheet is empty or not found. Proceeding without annual load factor targets.")
        except Exception as e:
            print(f"Info: Could not process 'load_factors' sheet (optional): {e}")
            annual_load_factor_targets_df = None # Ensure it's None if any error

    except FileNotFoundError:
        print(f"Error: File not found at '{file_path}'.")
        return None
    except Exception as e: # Catch other general file reading errors
        print(f"An unexpected error occurred while trying to read '{file_path}': {e}")
        return None

    print("Load curve template data loading and processing completed.")
    return {
        'past_hourly_demand': past_hourly_demand_df,
        'total_annual_demand': total_annual_demand_df,
        'monthly_peak_targets': monthly_peak_targets_df if monthly_peak_targets_df is not None else pd.DataFrame(), # Return empty DF if None
        'annual_load_factor_targets': annual_load_factor_targets_df if annual_load_factor_targets_df is not None else pd.DataFrame() # Return empty DF if None
    }

if __name__ == '__main__':
    # This section is for testing the parser directly.
    # It requires a sample Excel file named 'load_curve_template_test.xlsx'.

    # Example of creating a dummy Excel file for testing:
    # try:
    #     writer = pd.ExcelWriter("load_curve_template_test.xlsx", engine='openpyxl')

    #     # Past_Hourly_Demand sheet
    #     hourly_data = {
    #         'date': pd.to_datetime(['2023-01-01', '2023-01-01', '2023-01-01', '2023-01-02']),
    #         'time': ['00:00:00', '01:00:00', '00:30:00', '00:00:00'], # Includes a sub-hourly for testing resample
    #         'demand': [100, 110, 50, 90] # 50 at 00:30 should be summed with 100 at 00:00 for hour 0
    #     }
    #     pd.DataFrame(hourly_data).to_excel(writer, sheet_name="Past_Hourly_Demand", index=False)

    #     # Total Demand sheet
    #     total_demand_data = {
    #         'Financial_Year': ['2021-22', '2022-23', '2023-24'],
    #         'Total Demand MU': [12000, 12500, 13000]
    #     }
    #     pd.DataFrame(total_demand_data).to_excel(writer, sheet_name="Total Demand", index=False)

    #     # max_demand sheet
    #     max_demand_data = {
    #         'Financial_Year': ['2022-23', '2023-24'],
    #         'Apr': [2000, 2100], 'May': [2100, 2200], 'Jun': [1900, 2000],
    #         'Jul': [1800, 1900], 'Aug': [1850, 1950], 'Sep': [1950, 2050],
    #         'Oct': [2050, 2150], 'Nov': [2000, 2100], 'Dec': [1900, 2000],
    #         'Jan': [1950, 2050], 'Feb': [1850, 1950], 'Mar': [2000, 2100]
    #     }
    #     pd.DataFrame(max_demand_data).to_excel(writer, sheet_name="max_demand", index=False)

    #     # load_factors sheet
    #     load_factors_data = {
    #         'FY': ['2022-23', '2023-24'],
    #         'Load Factor': [0.65, 0.66]
    #     }
    #     pd.DataFrame(load_factors_data).to_excel(writer, sheet_name="load_factors", index=False)

    #     writer.close()
    #     print("Dummy 'load_curve_template_test.xlsx' created for testing.")
    # except ImportError:
    #     print("Error: `openpyxl` is required to create the test Excel file. Please install it.")
    # except Exception as e_create:
    #     print(f"Could not create dummy excel: {e_create}")


    test_file_path_lc = "load_curve_template_test.xlsx"

    import os
    if os.path.exists(test_file_path_lc):
        print(f"\n--- Testing Load Curve Template parser with '{test_file_path_lc}' ---")
        parsed_lc_data = process_load_curve_template(test_file_path_lc)

        if parsed_lc_data:
            print("\n--- Parsed Load Curve Data Summary ---")
            print(f"\nPast Hourly Demand (head):\n{parsed_lc_data['past_hourly_demand'].head()}")
            if not parsed_lc_data['past_hourly_demand'].empty:
                 print(f"Timezone of past_hourly_demand index: {parsed_lc_data['past_hourly_demand'].index.tz}")


            print(f"\nTotal Annual Demand:\n{parsed_lc_data['total_annual_demand']}")

            if not parsed_lc_data['monthly_peak_targets'].empty:
                print(f"\nMonthly Peak Targets:\n{parsed_lc_data['monthly_peak_targets']}")
            else:
                print("\nMonthly Peak Targets: Not found or empty.")

            if not parsed_lc_data['annual_load_factor_targets'].empty:
                print(f"\nAnnual Load Factor Targets:\n{parsed_lc_data['annual_load_factor_targets']}")
            else:
                print("\nAnnual Load Factor Targets: Not found or empty.")
        else:
            print("\n--- Load Curve Parser Test Failed: No data returned ---")
    else:
        print(f"\n--- Load Curve Parser Test Skipped: Test file '{test_file_path_lc}' not found. ---")
        print("Please create 'load_curve_template_test.xlsx' or provide path to a valid file for testing.")
