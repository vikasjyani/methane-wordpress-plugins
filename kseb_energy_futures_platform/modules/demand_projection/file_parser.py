"""
Parses the demand input Excel file for the Demand Projection module.

This module contains functions to read and process data from various sheets
within the input_demand_file.xlsx, including settings, sector consumption,
and economic indicators. It extracts tables based on specified markers and
aggregates data as needed for further use in demand forecasting.
"""
import pandas as pd
import functools # For using reduce to merge DataFrames

# TODO: Add logging configuration if a platform-wide logger is established.
# For now, will use print for warnings/errors during parsing.

def extract_table_from_sheet_by_marker(sheet_df: pd.DataFrame, start_marker: str, end_marker: str = None) -> pd.DataFrame | None:
    """
    Extracts a table from a given sheet (as DataFrame) based on start and optional end markers.

    The function searches for the `start_marker` string in the first column of the sheet.
    The row after the marker is considered the header row for the table.
    Data is read until an empty row in the first column is encountered, or until
    the `end_marker` is found (if provided).

    Args:
        sheet_df (pd.DataFrame): The DataFrame representing the Excel sheet, read without headers.
        start_marker (str): The string indicating the start of the table (e.g., "~Settings Table").
        end_marker (str, optional): The string indicating the end of the table. If None,
                                    reading stops at the first empty row in the first column.

    Returns:
        pd.DataFrame | None: A DataFrame containing the extracted table, with headers set
                             from the row after the start_marker. Returns None if the
                             start_marker is not found or the table is empty.
    """
    if sheet_df.empty:
        print(f"Warning: Sheet is empty, cannot extract table for marker '{start_marker}'.")
        return None

    try:
        start_row_index = -1
        # Iterate to find the marker row explicitly
        for idx, row_val in enumerate(sheet_df.iloc[:, 0]):
            if isinstance(row_val, str) and row_val.strip() == start_marker.strip():
                start_row_index = idx
                break

        if start_row_index == -1:
            print(f"Debug: For marker '{start_marker}', first column data (up to 5 rows):\n{sheet_df.iloc[:5, 0].astype(str).str.strip().tolist()}")
            print(f"Warning: Start marker '{start_marker}' not found in the sheet by exact cell match.")
            return None
        header_row_index = start_row_index + 1

        if header_row_index >= len(sheet_df):
            print(f"Warning: No header row found after marker '{start_marker}'.")
            return None

        # Set the header from the row after the marker
        headers = sheet_df.iloc[header_row_index].astype(str).tolist()

        # Determine the start of actual data
        data_start_row_index = header_row_index + 1
        if data_start_row_index >= len(sheet_df):
            print(f"Warning: No data found after header for marker '{start_marker}'.")
            # Return an empty DataFrame with correct headers if no data rows
            return pd.DataFrame(columns=headers)

        # Determine the end of the table
        data_end_row_index = len(sheet_df) # Default to end of sheet

        if end_marker:
            end_marker_rows = sheet_df[sheet_df.iloc[:, 0] == end_marker].index
            if end_marker_rows.any() and end_marker_rows[0] > data_start_row_index:
                data_end_row_index = end_marker_rows[0]
        else:
            # Find the first empty row in the first column after data_start_row_index
            # Fill NaN to handle various empty cell representations, then check for empty string
            first_col_after_data_start = sheet_df.iloc[data_start_row_index:, 0].fillna('').astype(str)
            empty_row_indices = first_col_after_data_start[first_col_after_data_start == ''].index
            if not empty_row_indices.empty:
                data_end_row_index = empty_row_indices[0]

        # Slice the data for the table
        table_data_df = sheet_df.iloc[data_start_row_index:data_end_row_index]

        if table_data_df.empty:
            print(f"Info: Table for marker '{start_marker}' is present but contains no data rows.")
            return pd.DataFrame(columns=headers) # Return empty DataFrame with headers

        table_data_df.columns = headers
        table_data_df = table_data_df.reset_index(drop=True)

        # Optional: Clean up columns that might be entirely NaN or empty strings if they were beyond actual table width
        # For now, this is not implemented to keep it simpler. User should ensure clean Excel structure.

        return table_data_df

    except Exception as e:
        print(f"Error extracting table for marker '{start_marker}': {e}")
        return None


def load_input_demand_data(file_path: str) -> dict | None:
    """
    Loads and processes data from the demand input Excel file.

    This function reads multiple sheets ('main', 'Economic_Indicators', and individual
    sector sheets) from the specified Excel file. It extracts settings,
    sector names, econometric parameters, and historical consumption data for each sector.
    If econometric parameters are enabled, it merges them with the respective sector data.
    Finally, it aggregates electricity consumption across all sectors.

    Args:
        file_path (str): The path to the input Excel file (e.g., 'input_demand_file.xlsx').

    Returns:
        dict | None: A dictionary containing the processed data:
            'settings' (dict): General settings from the 'main' sheet.
            'sectors_list' (list): List of sector names.
            'econometric_map' (dict, optional): Mapping of sectors to their economic indicators.
            'economic_indicators_raw' (pd.DataFrame, optional): Raw DataFrame of economic indicators.
            'sector_data' (dict): Dictionary where keys are sector names and values are
                                  DataFrames of their respective data (possibly merged with indicators).
            'aggregated_electricity' (pd.DataFrame): DataFrame with 'Year' and electricity
                                                    consumption for each sector, plus a 'Total'.
            Returns None if critical errors occur (e.g., file not found, 'main' sheet missing).
    """
    print(f"Loading demand input data from: {file_path}")
    settings_dict = {}
    sector_names = []
    econometric_map_dict = {}
    eco_indicators_df = pd.DataFrame()
    sector_data_dict = {}
    aggregated_ele_list = []

    try:
        # Read the 'main' sheet first, without assuming headers for marker-based extraction
        main_sheet_df = pd.read_excel(file_path, sheet_name='main', header=None)
    except FileNotFoundError:
        print(f"Error: File not found at '{file_path}'.")
        return None
    except Exception as e: # Catch other pandas read_excel errors like invalid sheet name
        print(f"Error reading 'main' sheet from '{file_path}': {e}")
        return None

    # 1. Process '~Settings Table'
    settings_table_df = extract_table_from_sheet_by_marker(main_sheet_df, "~Settings Table")
    if settings_table_df is None or settings_table_df.empty:
        print("Error: '~Settings Table' could not be extracted or is empty. Cannot proceed.")
        return None

    try:
        # Assuming settings are key-value pairs in first two columns
        for _, row in settings_table_df.iterrows():
            key = str(row.iloc[0]).strip()
            value = str(row.iloc[1]).strip() if len(row) > 1 else ""
            settings_dict[key] = value

        # Convert specific settings to appropriate types
        settings_dict['Start_Year'] = int(settings_dict.get('Start_Year', 0))
        settings_dict['End_Year'] = int(settings_dict.get('End_Year', 0))
        settings_dict['Econometric_Parameters'] = settings_dict.get('Econometric_Parameters', 'No').capitalize()
        # Add more type conversions or validations as needed
    except Exception as e:
        print(f"Error processing settings from '~Settings Table': {e}. Settings found: {settings_dict}")
        return None

    print(f"Settings processed: {settings_dict}")

    # 2. Process '~Consumption_Sectors Table'
    sectors_table_df = extract_table_from_sheet_by_marker(main_sheet_df, "~Consumption_Sectors Table")
    if sectors_table_df is None or sectors_table_df.empty:
        print("Error: '~Consumption_Sectors Table' could not be extracted or is empty. Cannot proceed.")
        return None

    if 'Sector_Name' not in sectors_table_df.columns:
        print("Error: 'Sector_Name' column missing in '~Consumption_Sectors Table'.")
        return None
    sector_names = sectors_table_df['Sector_Name'].dropna().astype(str).tolist()
    if not sector_names:
        print("Error: No sector names found in '~Consumption_Sectors Table'.")
        return None
    print(f"Sectors found: {sector_names}")

    # 3. Process '~Econometric_Parameters Table' if enabled
    if settings_dict.get('Econometric_Parameters') == 'Yes':
        econometric_params_table_df = extract_table_from_sheet_by_marker(main_sheet_df, "~Econometric_Parameters Table")
        if econometric_params_table_df is None: # Can be empty if no params defined, but not an error
            print("Warning: Econometric parameters enabled, but '~Econometric_Parameters Table' not found or empty.")
        elif not econometric_params_table_df.empty:
            # Expects columns like 'Sector_Name', 'Indicator_1', 'Indicator_2', ...
            if 'Sector_Name' not in econometric_params_table_df.columns:
                print("Warning: 'Sector_Name' column missing in '~Econometric_Parameters Table'. Skipping.")
            else:
                for _, row in econometric_params_table_df.iterrows():
                    sector = str(row['Sector_Name']).strip()
                    if sector in sector_names:
                        # Collect all other columns as indicators for this sector
                        indicators = [str(val).strip() for col, val in row.items() if col != 'Sector_Name' and pd.notna(val) and str(val).strip()]
                        if indicators:
                            econometric_map_dict[sector] = indicators
                print(f"Econometric parameter map: {econometric_map_dict}")

        # 4. Read 'Economic_Indicators' sheet if econometric parameters are enabled
        try:
            eco_indicators_df = pd.read_excel(file_path, sheet_name='Economic_Indicators')
            if 'Year' not in eco_indicators_df.columns and not eco_indicators_df.empty:
                print("Warning: 'Year' column not found in 'Economic_Indicators' sheet. If data exists, first row values might be used as constants.")
            elif eco_indicators_df.empty:
                 print("Warning: 'Economic_Indicators' sheet is empty.")
        except Exception as e:
            print(f"Warning: Could not read 'Economic_Indicators' sheet: {e}. Proceeding without it.")
            eco_indicators_df = pd.DataFrame() # Ensure it's an empty DataFrame

    # 5. Process individual Sector Sheets
    for sector_name in sector_names:
        try:
            sector_df = pd.read_excel(file_path, sheet_name=sector_name)
            if 'Year' not in sector_df.columns or 'Electricity' not in sector_df.columns:
                print(f"Warning: Sector sheet '{sector_name}' is missing 'Year' or 'Electricity' column. Skipping this sector.")
                continue

            # Ensure 'Year' is integer for merging
            sector_df['Year'] = sector_df['Year'].astype(int)

            # Merge econometric indicators if applicable
            if settings_dict.get('Econometric_Parameters') == 'Yes' and sector_name in econometric_map_dict and not eco_indicators_df.empty:
                indicators_for_sector = econometric_map_dict[sector_name]
                relevant_eco_indicators = [col for col in indicators_for_sector if col in eco_indicators_df.columns]

                if not relevant_eco_indicators:
                    print(f"Warning: No matching economic indicators found in 'Economic_Indicators' sheet for sector '{sector_name}' (requested: {indicators_for_sector}).")
                else:
                    eco_subset_df = eco_indicators_df[['Year'] + relevant_eco_indicators].copy() if 'Year' in eco_indicators_df.columns else eco_indicators_df[relevant_eco_indicators].copy()

                    if 'Year' in eco_indicators_df.columns:
                        eco_subset_df['Year'] = eco_subset_df['Year'].astype(int)
                        sector_df = pd.merge(sector_df, eco_subset_df, on='Year', how='left')
                        print(f"Merged economic indicators for sector '{sector_name}' on 'Year'.")
                    elif not eco_subset_df.empty: # No 'Year' col, apply first row of indicators as constants
                        print(f"Applying first row of economic indicators as constants for sector '{sector_name}'.")
                        for indicator_col in relevant_eco_indicators:
                            if not eco_subset_df[indicator_col].empty:
                                sector_df[indicator_col] = eco_subset_df[indicator_col].iloc[0]
                            else:
                                sector_df[indicator_col] = pd.NA # Or some default like 0 or NaN

            sector_data_dict[sector_name] = sector_df

            # Prepare data for aggregation: 'Year' and 'Electricity' renamed to 'SectorName_Electricity'
            if 'Electricity' in sector_df.columns and 'Year' in sector_df.columns:
                agg_sector_df = sector_df[['Year', 'Electricity']].copy()
                agg_sector_df.rename(columns={'Electricity': f"{sector_name.replace(' ', '_')}_Electricity"}, inplace=True)
                aggregated_ele_list.append(agg_sector_df)
            else:
                print(f"Warning: Could not prepare aggregation data for sector '{sector_name}' due to missing columns.")

        except Exception as e: # Catch errors reading individual sector sheets
            print(f"Error processing sector sheet '{sector_name}': {e}. Skipping this sector.")
            continue # Skip to next sector

    # 6. Aggregate Electricity Consumption
    aggregated_ele_df = pd.DataFrame(columns=['Year', 'Total_Electricity']) # Default empty
    if aggregated_ele_list:
        try:
            # Merge all sector electricity dataframes on 'Year'
            # Using functools.reduce for merging a list of DataFrames
            merged_df = functools.reduce(lambda left, right: pd.merge(left, right, on='Year', how='outer'), aggregated_ele_list)

            # Calculate 'Total' column by summing all electricity columns (those ending with '_Electricity')
            ele_cols = [col for col in merged_df.columns if col.endswith('_Electricity')]
            merged_df['Total_Electricity'] = merged_df[ele_cols].sum(axis=1, skipna=True)
            merged_df = merged_df.sort_values(by='Year').reset_index(drop=True) # Sort by year
            aggregated_ele_df = merged_df
        except Exception as e:
            print(f"Error during aggregation of electricity data: {e}")
            # Fallback to an empty DataFrame with expected columns if aggregation fails
            aggregated_ele_df = pd.DataFrame(columns=['Year'] + [col for df in aggregated_ele_list for col in df.columns if col != 'Year'] + ['Total_Electricity'])

    print("Demand data loading and processing completed.")
    return {
        'settings': settings_dict,
        'sectors_list': sector_names,
        'econometric_map': econometric_map_dict if settings_dict.get('Econometric_Parameters') == 'Yes' else {},
        'economic_indicators_raw': eco_indicators_df if settings_dict.get('Econometric_Parameters') == 'Yes' else pd.DataFrame(),
        'sector_data': sector_data_dict,
        'aggregated_electricity': aggregated_ele_df
    }

if __name__ == '__main__':
    # This section is for testing the parser directly.
    # It requires a sample Excel file named 'input_demand_file.xlsx' in the same directory or a specified path.
    # Create a dummy Excel file for testing if you don't have one.

    # Example of creating a dummy Excel file for testing:
    # (You would typically run this once to create the file, then comment it out)

    # try:
    #     writer = pd.ExcelWriter("input_demand_file_test.xlsx", engine='openpyxl')

    #     # Main sheet
    #     main_data = {
    #         0: ["~Settings Table", "Setting", "Value", None, "~Consumption_Sectors Table", "Sector_Name", "Description", None, "~Econometric_Parameters Table", "Sector_Name", "Indicator_1", "Indicator_2"],
    #         1: [None, "Start_Year", "2018", None, None, "Residential", "Housing units", None, None, "Residential", "GDP_Total", "Population_Urban"],
    #         2: [None, "End_Year", "2023", None, None, "Commercial", "Commercial floor space", None, None, "Commercial", "GVA_Services", None],
    #         3: [None, "Econometric_Parameters", "Yes", None, None, "Industrial", "Industrial output index", None, None, "Industrial", "IIP_Index", "Energy_Price_Industrial"],
    #         4: [None, "Forecast_Model", "ARIMA", None, None, None, None, None, None, None, None, None],
    #         5: [None, None, None, None, "~End_Consumption_Sectors Table", None, None, None, None, None, None, None], # End marker for sectors
    #         6: [None, None, None, None, None, None, None, None, "~End_Econometric_Parameters Table", None, None, None] # End marker for econ params
    #     }
    #     pd.DataFrame(main_data).T.to_excel(writer, sheet_name="main", index=False, header=False) # Transpose for correct structure

    #     # Economic Indicators sheet
    #     eco_ind_data = {
    #         'Year': [2018, 2019, 2020, 2021, 2022, 2023],
    #         'GDP_Total': [100, 105, 95, 102, 108, 112],
    #         'Population_Urban': [50, 51, 52, 53, 54, 55],
    #         'GVA_Services': [60, 63, 58, 62, 65, 68],
    #         'IIP_Index': [110, 112, 105, 108, 115, 118],
    #         'Energy_Price_Industrial': [5.0, 5.2, 5.1, 5.3, 5.5, 5.6]
    #     }
    #     pd.DataFrame(eco_ind_data).to_excel(writer, sheet_name="Economic_Indicators", index=False)

    #     # Sector sheets
    #     res_data = {'Year': [2018, 2019, 2020, 2021, 2022, 2023], 'Electricity': [1000, 1050, 950, 1020, 1080, 1120], 'Other_Fuel': [50,50,40,45,50,50]}
    #     pd.DataFrame(res_data).to_excel(writer, sheet_name="Residential", index=False)
    #     com_data = {'Year': [2018, 2019, 2020, 2021, 2022, 2023], 'Electricity': [800, 830, 750, 790, 840, 880]}
    #     pd.DataFrame(com_data).to_excel(writer, sheet_name="Commercial", index=False)
    #     ind_data = {'Year': [2018, 2019, 2020, 2021, 2022, 2023], 'Electricity': [1200, 1250, 1100, 1180, 1300, 1350]}
    #     pd.DataFrame(ind_data).to_excel(writer, sheet_name="Industrial", index=False)

    #     writer.close() # Use close with XlsxWriter, save for openpyxl
    #     print("Dummy 'input_demand_file_test.xlsx' created for testing.")
    # except ImportError:
    #     print("Error: `openpyxl` is required to create the test Excel file. Please install it.")
    # except Exception as e_create:
    #     print(f"Could not create dummy excel: {e_create}")


    test_file_path = "input_demand_file_test.xlsx" # Assumes it's in the same directory as this script

    # Check if test file exists, otherwise skip test.
    import os
    if os.path.exists(test_file_path):
        print(f"\n--- Testing parser with '{test_file_path}' ---")
        parsed_data = load_input_demand_data(test_file_path)

        if parsed_data:
            print("\n--- Parsed Data Summary ---")
            print(f"\nSettings: {parsed_data['settings']}")
            print(f"\nSectors List: {parsed_data['sectors_list']}")
            if parsed_data['settings'].get('Econometric_Parameters') == 'Yes':
                print(f"\nEconometric Map: {parsed_data['econometric_map']}")
                print(f"\nEconomic Indicators Raw DF (head):\n{parsed_data['economic_indicators_raw'].head()}")

            print("\nSector Data (showing head for 'Residential' if exists):")
            if "Residential" in parsed_data['sector_data']:
                print(parsed_data['sector_data']["Residential"].head())
            else:
                print("Residential sector data not found or not parsed.")

            print(f"\nAggregated Electricity Consumption (head):\n{parsed_data['aggregated_electricity'].head()}")
            print(f"\nAggregated Electricity Consumption (tail):\n{parsed_data['aggregated_electricity'].tail()}")
            print(f"\nAggregated Electricity Consumption Columns: {parsed_data['aggregated_electricity'].columns.tolist()}")

        else:
            print("\n--- Parser Test Failed: No data returned ---")
    else:
        print(f"\n--- Parser Test Skipped: Test file '{test_file_path}' not found. ---")
        print("Please create 'input_demand_file_test.xlsx' or provide path to a valid file for testing.")
